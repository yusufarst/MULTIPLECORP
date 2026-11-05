<?php

namespace App\Modules\ImportOrchestration\Actions;

use App\Modules\DOC\Services\EvidenceRegistry;
use App\Modules\ImportOrchestration\Models\Batch as ImportBatch;
use App\Modules\ImportOrchestration\Services\Batches;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;

/**
 * `IMPORT SignOffImport` (ROUTE-274; INT-274; NX-13; L-33; IMP-05): "Setujui Hasil Uji Coba" on a batch whose dry run
 * passed — each account records its own sign-off with its sign-off evidence, the account's own upload registered as
 * pool OPENING_SIGNOFF evidence linked to the batch: the Owner's as the Owner's, anyone else's in scope as the
 * Admin's —; the batch SIGNED_OFF, *Disetujui Bersama*, once both exist.
 */
final class SignOffImport
{
    public const TYPE = 'IMPORT.SignOffImport';

    public const CAPABILITY = 'import.prepare';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly EvidenceRegistry $evidence,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, ImportBatch $batch, string $uploadPublicId): Outcome
    {
        $file = $this->evidence->ownUpload($actorUserId, $uploadPublicId, null);
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $batch->company_scope[0] ?? null, self::CAPABILITY,
            Fingerprint::of(self::TYPE, null, ['batch' => $batch->public_id, 'file' => $file->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($batch, $file): CommandResult {
            $context->lock(Batches::locks($batch));
            $authority = Batches::authorize($context, $batch);
            $row = Batches::fresh($batch);

            if ($row->state !== 'DRY_RUN_PASSED') {
                throw Refused::rejected('ST-02', ['state' => $row->state]);
            }

            $owner = $authority->allows('import.commit');
            $by = $owner ? 'owner_signoff_by' : 'admin_signoff_by';

            if ($row->getAttribute($by) !== null) {
                throw Refused::rejected('L-33', ['reason' => 'signed']);
            }

            $now = $context->now();
            $actor = $context->request->actorUserId;
            $evidence = $this->evidence->registerOpeningSignoff($context, $file, $row->id, $row->public_id);
            $other = $owner ? $row->admin_signoff_by : $row->owner_signoff_by;
            $context->connection->table('import_batches')->where('id', $row->id)->update([
                $by => $actor, ($owner ? 'owner_signoff_at' : 'admin_signoff_at') => $now, 'state' => $other === null ? 'DRY_RUN_PASSED' : 'SIGNED_OFF',
                'lock_version' => $row->lock_version + 1, 'updated_at' => $now,
            ]);
            $this->audit->record(
                action: 'IMPORT.BatchSignedOff',
                authorityClass: $owner ? AuthorityClass::OwnerOnly : AuthorityClass::Adm,
                actorUserId: $actor,
                companyId: $row->company_scope[0] ?? null,
                entityType: 'import_batch',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                commandId: $context->request->commandId,
                change: AuditChange::updated(['state' => $row->state], ['state' => $other === null ? 'DRY_RUN_PASSED' : 'SIGNED_OFF', 'signed_as' => $owner ? 'owner' : 'admin']),
                metadata: ['evidence' => $evidence],
            );

            return new CommandResult('import_batch', $row->id);
        });
    }
}
