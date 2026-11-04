<?php

namespace App\Modules\ImportOrchestration\Sweeps;

use App\Modules\ImportOrchestration\Actions\DryRunImport;
use App\Modules\ImportOrchestration\Jobs\CommitImportRows;
use App\Modules\ImportOrchestration\Jobs\ValidateImportBatch;
use App\Modules\ImportOrchestration\Models\Batch as ImportBatch;
use App\Support\Jobs\OwedWork;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * JB-09 for the import (CONCURRENCY_IDEMPOTENCY §15; M-34; HO-18): a dry run still PENDING after its job's time, and
 * a commit request whose batch is neither finished nor stopped and has written no row for a while — a lost dispatch or
 * a dead chunk —, re-dispatched from the batch's own durable state (IX-15); the jobs' uniqueness and the rows'
 * identities make a second dispatch harmless.
 */
final class ImportSweep implements OwedWork
{
    /** Minutes a commit may write nothing before its chunk is taken for lost. */
    public const STALE_MINUTES = 5;

    public function sweep(): int
    {
        $now = CarbonImmutable::now();
        $acted = 0;

        foreach (ImportBatch::query()->where('state', 'PREPARED')->whereRaw("dry_run_report->>'state' = ?", [DryRunImport::PENDING])
            ->where('updated_at', '<=', $now->subSeconds(ValidateImportBatch::TIMEOUT))->get() as $batch) {
            $request = is_array($batch->dry_run_report) && is_string($batch->dry_run_report['request'] ?? null) ? $batch->dry_run_report['request'] : null;

            if ($request !== null) {
                ValidateImportBatch::dispatch($batch->id, $request);
                $acted++;
            }
        }

        foreach (ImportBatch::query()->where('state', 'SIGNED_OFF')->whereNotNull('committed_at')->where('committed_at', '<=', $now->subMinutes(self::STALE_MINUTES))->get() as $batch) {
            $last = DB::table('import_rows')->where('import_batch_id', $batch->id)->max('updated_at');

            if (CommitImportRows::stopped($batch->id) || (is_string($last) && CarbonImmutable::parse($last)->greaterThan($now->subMinutes(self::STALE_MINUTES)))) {
                continue;
            }

            CommitImportRows::dispatch($batch->id);
            $acted++;
        }

        return $acted;
    }
}
