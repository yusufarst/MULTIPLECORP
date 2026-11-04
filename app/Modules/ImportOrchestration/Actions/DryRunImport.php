<?php

namespace App\Modules\ImportOrchestration\Actions;

use App\Modules\ImportOrchestration\Jobs\ValidateImportBatch;
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
use Illuminate\Support\Str;

/**
 * `IMPORT DryRunImport` (ROUTE-273; INT-273; NX-13; JB-05; IMP-04): "Jalankan Uji Coba" — and "Jalankan Uji Coba Lagi"
 * after a failed validation — for a batch not yet signed off: the request recorded on the batch, *Uji Coba Berjalan*,
 * and the validation job dispatched once it commits, its state polled (IP-12). `import.prepare` with every company of
 * the batch in scope (PJ-20).
 */
final class DryRunImport
{
    public const TYPE = 'IMPORT.DryRunImport';

    public const CAPABILITY = 'import.prepare';

    public const PENDING = 'PENDING';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, ImportBatch $batch): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $batch->company_scope[0] ?? null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, ['batch' => $batch->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($batch): CommandResult {
            $context->lock(Batches::locks($batch));
            Batches::authorize($context, $batch);
            $row = Batches::fresh($batch);

            if (! in_array($row->state, ['PREPARED', 'DRY_RUN_PASSED'], true)) {
                throw Refused::rejected('ST-02', ['state' => $row->state]);
            }

            $token = (string) Str::uuid();
            $context->connection->table('import_batches')->where('id', $row->id)->update([
                'state' => 'PREPARED', 'lock_version' => $row->lock_version + 1, 'updated_at' => $context->now(),
                'dry_run_report' => json_encode(['state' => self::PENDING, 'request' => $token, 'requested_at' => $context->now()->toIso8601String(), 'requested_by' => $context->request->actorUserId], JSON_THROW_ON_ERROR),
            ]);
            $this->audit->record(
                action: 'IMPORT.DryRunRequested',
                authorityClass: AuthorityClass::Adm,
                actorUserId: $context->request->actorUserId,
                companyId: $row->company_scope[0] ?? null,
                entityType: 'import_batch',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                commandId: $context->request->commandId,
                change: AuditChange::updated(['state' => $row->state], ['state' => 'PREPARED', 'dry_run' => self::PENDING]),
            );
            $id = $row->id;
            $context->afterCommit(static fn () => ValidateImportBatch::dispatch($id, $token));

            return new CommandResult('import_batch', $row->id);
        });
    }
}
