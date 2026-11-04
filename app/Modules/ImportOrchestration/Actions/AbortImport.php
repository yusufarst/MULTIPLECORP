<?php

namespace App\Modules\ImportOrchestration\Actions;

use App\Modules\ImportOrchestration\Services\Batches;
use App\Modules\OPS\Models\ImportBatch;
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
 * `IMPORT AbortImport` (ROUTE-276; INT-276; NX-13; `DIR-100`; IMP-06; PRQ-06): the Owner's "Batalkan Batch" with its
 * reason, behind the step-up, for a batch that has committed no row — ABORTED and kept with its results, so that none
 * of its rows commits afterwards (a row's commit re-reads the batch under lock); REJECTED for a batch with a committed
 * or begun row, whose wrong facts are corrected on their records instead (CM-33). `import.commit` (OWNER_ONLY).
 */
final class AbortImport
{
    public const TYPE = 'IMPORT.AbortImport';

    public const CAPABILITY = 'import.commit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, ImportBatch $batch, string $reason): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $batch->company_scope[0] ?? null, self::CAPABILITY,
            Fingerprint::of(self::TYPE, null, ['batch' => $batch->public_id, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($batch, $reason): CommandResult {
            $context->lock(Batches::locks($batch));
            Batches::authorize($context, $batch);
            $row = Batches::fresh($batch);

            if (! in_array($row->state, ['PREPARED', 'DRY_RUN_PASSED', 'SIGNED_OFF'], true)) {
                throw Refused::rejected('ST-02', ['state' => $row->state]);
            }

            if (Batches::started($row->id)) {
                throw Refused::rejected('IMP-06', ['reason' => 'committed']);
            }

            $context->connection->table('import_batches')->where('id', $row->id)->update(['state' => 'ABORTED', 'lock_version' => $row->lock_version + 1, 'updated_at' => $context->now()]);
            $this->audit->record(
                action: 'IMPORT.BatchAborted',
                authorityClass: AuthorityClass::OwnerOnly,
                actorUserId: $context->request->actorUserId,
                companyId: $row->company_scope[0] ?? null,
                entityType: 'import_batch',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                reason: trim($reason),
                commandId: $context->request->commandId,
                change: AuditChange::updated(['state' => $row->state], ['state' => 'ABORTED']),
            );

            return new CommandResult('import_batch', $row->id);
        });
    }
}
