<?php

namespace App\Modules\ImportOrchestration\Actions;

use App\Modules\ImportOrchestration\Jobs\CommitImportRows;
use App\Modules\ImportOrchestration\Kinds\SeedRows;
use App\Modules\ImportOrchestration\Services\Batches;
use App\Modules\ImportOrchestration\Services\RowRunner;
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
 * `IMPORT CommitImport` (ROUTE-275; INT-275; NX-13; AX-28; OD-13; IMP-05; IMP-06): the Owner's "Jalankan Impor" — and
 * "Lanjutkan" — behind the irreversible confirmation and the step-up, for a batch both sign-offs hold: a batch lacking a
 * seed row for a period its cutoff falls into is refused with the missing type and period (NM-03); otherwise the commit
 * request is recorded on the batch, the refusal a stopped row carries is cleared, and JB-07 commits the rows once this
 * commits. Another Owner may resume (M-33). `import.commit` (OWNER_ONLY).
 */
final class CommitImport
{
    public const TYPE = 'IMPORT.CommitImport';

    public const CAPABILITY = 'import.commit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly SeedRows $seeds,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, ImportBatch $batch): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $batch->company_scope[0] ?? null, self::CAPABILITY, Fingerprint::of(self::TYPE, null, ['batch' => $batch->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($batch): CommandResult {
            $context->lock(Batches::locks($batch));
            Batches::authorize($context, $batch);
            $row = Batches::fresh($batch);

            if ($row->state !== 'SIGNED_OFF') {
                throw Refused::rejected(in_array($row->state, ['PREPARED', 'DRY_RUN_PASSED'], true) ? 'L-33' : 'ST-02', ['state' => $row->state]);
            }

            $missing = $this->seeds->missing($row->id, $row->company_scope, $row->cutoff_date->format('Y-m-d'));

            if ($missing !== null) {
                throw Refused::rejected('NM-03', ['jenis' => $missing['type'], 'periode' => $missing['period']]);
            }

            $now = $context->now();
            $resumed = $row->committed_at !== null;
            // A resume clears the refusal or failure recorded on the row it resumes from (NX-13); a row begun keeps its mark.
            $context->connection->table('import_rows')->where('import_batch_id', $row->id)->where('validation_state', 'VALID')->whereNotNull('errors')
                ->whereRaw('not (errors @> ?::jsonb)', [json_encode([['reason' => RowRunner::IN_PROGRESS]])])->update(['errors' => null, 'updated_at' => $now]);
            $context->connection->table('import_batches')->where('id', $row->id)->update([
                'committed_by' => $context->request->actorUserId, 'committed_at' => $now, 'lock_version' => $row->lock_version + 1, 'updated_at' => $now,
            ]);
            $this->audit->record(
                action: $resumed ? 'IMPORT.CommitResumed' : 'IMPORT.CommitRequested',
                authorityClass: AuthorityClass::OwnerOnly,
                actorUserId: $context->request->actorUserId,
                companyId: $row->company_scope[0] ?? null,
                entityType: 'import_batch',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                commandId: $context->request->commandId,
                change: AuditChange::updated(['committed' => $resumed], ['committed' => true]),
                businessDate: $row->cutoff_date->format('Y-m-d'),
                metadata: ['companies' => $row->company_scope, 'kind' => $row->import_kind],
            );
            $id = $row->id;
            $context->afterCommit(static fn () => CommitImportRows::dispatch($id));

            return new CommandResult('import_batch', $row->id);
        });
    }
}
