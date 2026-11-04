<?php

namespace App\Modules\ImportOrchestration\Jobs;

use App\Modules\ImportOrchestration\Actions\DryRunImport;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\ImportOrchestration\Services\Batches;
use App\Modules\ImportOrchestration\Services\BatchRows;
use App\Modules\ImportOrchestration\Services\RowRunner;
use App\Modules\OPS\Models\ImportBatch;
use App\Support\Jobs\PlatformJob;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * JB-05, the dry run (IMP-04; DATABASE §24): every row not yet committed runs the same path its commit will — each
 * step its module's ordinary action — inside one transaction rolled back at the end, the isolated copy, an Active
 * Owner's authority standing in for the committer's; then, in a short transaction of its own, each row's result is
 * written — a row its commit would refuse INVALID with its reason, kept from the commit — with the control totals per
 * company and the report, the batch *Uji Coba Lolos* when no row is invalid and one is valid. A failed run is reported
 * with "Jalankan Uji Coba Lagi"; a request superseded meanwhile writes nothing.
 */
final class ValidateImportBatch extends PlatformJob implements ShouldBeUnique
{
    public const STAGE = 'dry_run';

    public int $tries = 1;

    public const TIMEOUT = 1800;

    public int $timeout = self::TIMEOUT;

    public int $uniqueFor = 1800;

    public function __construct(public readonly int $batchId, public readonly string $request)
    {
        parent::__construct();
    }

    public function uniqueId(): string
    {
        return "{$this->batchId}:{$this->request}";
    }

    public function handle(RowRunner $runner, BatchRows $rows): void
    {
        $batch = ImportBatch::query()->find($this->batchId);

        if ($batch === null || ! $this->current($batch)) {
            return;
        }

        // A row the last dry run refused is tried again; a row the source refused stays INVALID.
        $candidates = array_values(array_filter($rows->rows($batch, ['VALID', 'INVALID']), fn ($row): bool => $row->payload !== [] && $this->retryable($row->id)));
        $actor = Batches::owner();
        $results = [];

        DB::beginTransaction();

        try {
            foreach ($candidates as $row) {
                $results[$row->id] = $actor === null ? ['state' => 'REFUSED', 'errors' => [['field' => '', 'reason' => 'denied']]] : $runner->run($row, $actor, dryRun: true);
            }
        } finally {
            DB::rollBack();
        }

        $this->record($batch, $candidates, $results, $rows);
    }

    public function failed(?Throwable $error): void
    {
        DB::transaction(function (): void {
            $batch = ImportBatch::query()->whereKey($this->batchId)->lockForUpdate()->first();

            if ($batch !== null && $this->current($batch)) {
                $batch->forceFill(['dry_run_report' => ['state' => 'FAILED', 'request' => $this->request, 'finished_at' => CarbonImmutable::now()->toIso8601String()]])->save();
            }
        });
    }

    /**
     * @param  list<ImportRowData>  $candidates
     * @param  array<int, array{state: string, errors: list<array<string, mixed>>}>  $results
     */
    private function record(ImportBatch $batch, array $candidates, array $results, BatchRows $rows): void
    {
        DB::transaction(function () use ($batch, $candidates, $results, $rows): void {
            $locked = ImportBatch::query()->whereKey($batch->id)->lockForUpdate()->first();

            if ($locked === null || ! $this->current($locked) || $locked->state !== 'PREPARED') {
                return;
            }

            $now = CarbonImmutable::now();
            $valid = [];

            foreach ($candidates as $row) {
                $result = $results[$row->id] ?? null;

                if ($result === null) {
                    continue;
                }

                $passed = $result['state'] === 'COMMITTED';
                DB::table('import_rows')->where('id', $row->id)->whereIn('validation_state', ['VALID', 'INVALID'])->update([
                    'validation_state' => $passed ? 'VALID' : 'INVALID', 'updated_at' => $now,
                    'errors' => $passed ? null : json_encode(array_map(fn (array $error): array => [...$error, 'stage' => self::STAGE], $result['errors']), JSON_THROW_ON_ERROR),
                ]);

                if ($passed) {
                    $valid[] = $row;
                }
            }

            $counts = DB::table('import_rows')->where('import_batch_id', $batch->id)->groupBy('validation_state')->selectRaw('validation_state, count(*) as total')->pluck('total', 'validation_state')->all();
            $passed = ($counts['INVALID'] ?? 0) === 0 && ($counts['VALID'] ?? 0) > 0;
            $locked->forceFill([
                'state' => $passed ? 'DRY_RUN_PASSED' : 'PREPARED',
                'control_totals' => $rows->totals($valid),
                'dry_run_report' => ['state' => $passed ? 'PASSED' : 'INVALID', 'request' => $this->request, 'counts' => $counts, 'finished_at' => $now->toIso8601String()],
                'lock_version' => $locked->lock_version + 1,
            ])->save();
        });
    }

    /** Whether the batch still waits for this request (DryRunImport's token). */
    private function current(ImportBatch $batch): bool
    {
        $report = $batch->dry_run_report;

        return is_array($report) && ($report['state'] ?? null) === DryRunImport::PENDING && ($report['request'] ?? null) === $this->request;
    }

    /** A VALID row, or one only an earlier dry run refused. */
    private function retryable(int $rowId): bool
    {
        $row = DB::table('import_rows')->where('id', $rowId)->first(['validation_state', 'errors']);

        if ($row === null || $row->validation_state === 'VALID') {
            return $row !== null;
        }

        $errors = json_decode(is_string($row->errors) ? $row->errors : '[]', true);

        return is_array($errors) && $errors !== [] && array_filter($errors, fn (mixed $error): bool => ! is_array($error) || ($error['stage'] ?? null) !== self::STAGE) === [];
    }
}
