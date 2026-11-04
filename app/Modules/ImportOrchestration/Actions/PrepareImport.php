<?php

namespace App\Modules\ImportOrchestration\Actions;

use App\Modules\DOC\Models\FileObject;
use App\Modules\DOC\Services\FileStore;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ImportOrchestration\Kinds\Kinds;
use App\Modules\ImportOrchestration\Services\Preparation;
use App\Modules\ImportOrchestration\Services\SourceRefused;
use App\Modules\ImportOrchestration\Services\SourceTable;
use App\Modules\OPS\Models\ImportBatch;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandLog;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * `IMPORT PrepareImport` (ROUTE-272; INT-272; NX-13; WF-MIG-01; FL-06; C-47; DP-18): "Siapkan Batch" — the preparer's
 * own import-source upload (criterion 15), read as data only before any transaction, its kind, cutoff and companies,
 * every company in the preparer's `import.prepare` scope (PJ-20); the batch PREPARED with one import row per record,
 * malformed and duplicate rows surfaced with their numbers and reasons. The same source of the same kind is refused
 * (C-47); one whose earlier batch lies outside the preparer's scope answers MSG-18 and nothing more, recorded for the
 * Owner as IMPORT_COLLISION_UNDISCLOSED (UXS-19; LG-02).
 */
final class PrepareImport
{
    public const TYPE = 'IMPORT.PrepareImport';

    public const CAPABILITY = 'import.prepare';

    public const OUTSIDE_SCOPE = 'import_outside_scope';

    private const CHUNK = 500;

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly CommandLog $log,
        private readonly Kinds $kinds,
        private readonly Preparation $preparation,
        private readonly SourceTable $table,
        private readonly FileStore $files,
        private readonly EffectiveCapabilities $capabilities,
        private readonly SecurityEventRecorder $events,
        private readonly AuditRecorder $audit,
    ) {}

    /** @param  list<string>  $companyPublicIds */
    public function run(string $commandId, int $actorUserId, string $kind, string $filePublicId, string $cutoff, array $companyPublicIds): Outcome
    {
        // Criterion 15: the source is the preparer's own import-source upload; another account's answers as not found.
        $file = FileObject::query()->where('public_id', strtolower($filePublicId))->where('purpose', 'IMPORT_SOURCE')->where('uploaded_by', $actorUserId)->first()
            ?? throw (new ModelNotFoundException)->setModel(FileObject::class, [$filePublicId]);
        $publics = array_values(array_unique(array_map('strtolower', $companyPublicIds)));
        sort($publics);
        $companies = array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, DB::table('companies')->whereIn('public_id', $publics)->orderBy('id')->pluck('id')->all()));

        if ($companies === [] || count($companies) !== count($publics)) {
            throw (new ModelNotFoundException)->setModel(ImportBatch::class, $publics);
        }

        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companies[0], self::CAPABILITY,
            Fingerprint::of(self::TYPE, null, ['kind' => $kind, 'file' => $file->public_id, 'cutoff' => $cutoff, 'companies' => $publics]));
        $prepared = ['rows' => [], 'hidden' => []];

        // Parsed outside every transaction (ARCHITECTURE §15); a replay of a recorded outcome needs no parse.
        if ($this->log->probe($commandId) === null) {
            try {
                $path = $this->local($file);

                try {
                    $table = $this->table->read($path, strtolower(pathinfo($file->original_filename ?? '', PATHINFO_EXTENSION)));
                } finally {
                    @unlink($path);
                }
            } catch (SourceRefused $refused) {
                return Outcome::rejected('FL-06', ['reason' => $refused->reason]);
            }

            $authority = $this->capabilities->for($actorUserId);
            $codes = [];

            foreach (DB::table('companies')->get(['id', 'code']) as $company) {
                $codes[mb_strtolower(Row::text($company, 'code'))] = (int) Row::text($company, 'id');
            }

            $prepared = $this->preparation->rows($this->kinds->of($kind), $table, $companies, $codes,
                fn (?int $company): bool => $company === null || $authority->allows(self::CAPABILITY, $company));
        }

        return $this->envelope->run($request, function (CommandContext $context) use ($kind, $file, $cutoff, $companies, $prepared): CommandResult {
            $set = new LockSet;

            foreach ($companies as $company) {
                $set->add('companies', ['id' => $company]);
            }

            $context->lock($set);

            // PJ-20: every company of the batch in the preparer's scope; one outside refuses the batch (DP-18).
            foreach ($companies as $company) {
                if ($context->authority()?->allows(self::CAPABILITY, $company) !== true) {
                    throw new AuthorizationException;
                }
            }

            if (DB::table('import_batches')->where('source_file_id', $file->id)->exists()) {
                throw Refused::rejected('C-47', ['reason' => 'file']);
            }

            $this->collision($context, $kind, $file, $cutoff, $companies);
            $now = $context->now();
            $batch = new ImportBatch;
            $batch->forceFill([
                'import_kind' => $kind, 'source_file_id' => $file->id, 'source_sha256' => $file->sha256, 'cutoff_date' => $cutoff, 'company_scope' => $companies,
                'state' => 'PREPARED', 'prepared_by' => $context->request->actorUserId, 'command_id' => $context->request->commandId, 'command_line_no' => 1,
            ])->save();

            foreach (array_chunk($prepared['rows'], self::CHUNK) as $chunk) {
                $context->connection->table('import_rows')->insert(array_map(fn (array $row): array => [
                    ...$row, 'import_batch_id' => $batch->id, 'normalized_payload' => json_encode($row['normalized_payload'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'errors' => $row['errors'] === [] ? null : json_encode($row['errors'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'recorded_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }

            $counts = array_count_values(array_column($prepared['rows'], 'validation_state'));
            $this->audit->record(
                action: 'IMPORT.BatchPrepared',
                authorityClass: AuthorityClass::Adm,
                actorUserId: $context->request->actorUserId,
                companyId: $companies[0],
                entityType: 'import_batch',
                entityId: $batch->id,
                entityPublicId: $batch->public_id,
                commandId: $context->request->commandId,
                change: AuditChange::created(['kind' => $kind, 'cutoff' => $cutoff, 'rows' => count($prepared['rows'])]),
                businessDate: $cutoff,
                metadata: ['companies' => $companies, 'counts' => $counts],
            );
            $this->announce($context, $batch->public_id, $file->public_id, $companies, $prepared['hidden']);

            return new CommandResult('import_batch', $batch->id);
        });
    }

    /**
     * C-47's same source: an earlier batch of the kind with the same SHA-256 refuses it; when that batch spans a company
     * outside the preparer's scope, the answer is MSG-18 alone and the collision is recorded for the Owner (DP-18).
     *
     * @param  list<int>  $companies
     */
    private function collision(CommandContext $context, string $kind, FileObject $file, string $cutoff, array $companies): void
    {
        $twin = DB::table('import_batches')->where('import_kind', $kind)->where('source_sha256', $file->sha256)->first(['public_id', 'company_scope']);

        if ($twin === null) {
            return;
        }

        $scope = array_map(static fn (string $id): int => (int) $id, array_filter(explode(',', trim(is_string($twin->company_scope) ? $twin->company_scope : '', '{}')), 'is_numeric'));
        $outside = array_filter($scope, fn (int $company): bool => $context->authority()?->allows(self::CAPABILITY, $company) !== true);

        if ($outside === []) {
            throw Refused::rejected('C-47', ['reason' => 'source']);
        }

        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $related = is_string($twin->public_id) ? $twin->public_id : '';

        throw Refused::conflict(self::OUTSIDE_SCOPE, [], function (ConnectionInterface $connection) use ($actor, $commandId, $file, $related, $companies, $outside, $kind, $cutoff): void {
            $this->events->recordUndisclosed(SecurityEventKind::ImportCollisionUndisclosed, $actor, $commandId, 'file_object', $file->public_id, 'import_batch', $related,
                $companies[0], array_values($outside)[0], 'MSG-18', details: ['kind' => $kind, 'cutoff' => $cutoff, 'scope' => $companies]);
        });
    }

    /**
     * A row whose committed twin lies outside the preparer's scope, recorded for the Owner once the batch commits (LG-02; UXS-19).
     *
     * @param  list<int>  $companies
     * @param  list<array{identity: string, company: int|null, batch: string}>  $hidden
     */
    private function announce(CommandContext $context, string $batch, string $file, array $companies, array $hidden): void
    {
        foreach ($hidden as $twin) {
            $this->events->recordUndisclosed(SecurityEventKind::ImportCollisionUndisclosed, $context->request->actorUserId, $context->request->commandId, 'import_batch', $batch,
                'import_batch', $twin['batch'], $companies[0] ?? null, $twin['company'], 'MSG-18', details: ['identity' => $twin['identity'], 'file' => $file]);
        }
    }

    /** The stored source copied to a private temporary file, read there and removed. */
    private function local(FileObject $file): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import-');
        $source = $this->files->stream($file->storage_key, $file->storage_disk);
        $target = $path === false ? false : fopen($path, 'wb');

        if ($path === false || $target === false) {
            throw new SourceRefused(SourceRefused::UNREADABLE);
        }

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($target);
            fclose($source);
        }

        return $path;
    }
}
