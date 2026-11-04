<?php

namespace App\Modules\ImportOrchestration\Jobs;

use App\Modules\ImportOrchestration\Models\Batch as ImportBatch;
use App\Modules\ImportOrchestration\Services\BatchRows;
use App\Modules\ImportOrchestration\Services\RowRunner;
use App\Support\Jobs\PlatformJob;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * JB-07, the commit (AX-28; IMP-06; ARCHITECTURE §11): one chunk of the batch's VALID rows in commit order, each row
 * through its kind's steps under its deterministic identity as the Owner who requested the commit; a refused or
 * FAILED row stops the batch with its reason on its row — rows already committed stay committed (PX-05) — until an
 * Owner resumes; otherwise the next chunk is dispatched, and the batch is COMMITTED, *Selesai Diimpor*, when no VALID
 * row remains. A rerun finds its rows done (BD-11); a lost chunk is re-dispatched by the sweep (JB-09).
 */
final class CommitImportRows extends PlatformJob implements ShouldBeUniqueUntilProcessing
{
    /** Rows per chunk, sized so a chunk commits well within the job's timeout (IMP-06; the rehearsal resizes it). */
    public const CHUNK = 100;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $batchId)
    {
        parent::__construct();
    }

    public function uniqueId(): string
    {
        return (string) $this->batchId;
    }

    public function handle(RowRunner $runner, BatchRows $rows): void
    {
        $batch = ImportBatch::query()->find($this->batchId);

        if ($batch === null || $batch->state !== 'SIGNED_OFF' || $batch->committed_at === null || $batch->committed_by === null || self::stopped($batch->id)) {
            return;
        }

        $chunk = $rows->rows($batch, ['VALID'], self::CHUNK, withRefusals: false);

        if ($chunk === []) {
            $this->finish($batch);

            return;
        }

        foreach ($chunk as $row) {
            try {
                $result = $runner->run($row, $batch->committed_by, dryRun: false);
            } catch (Throwable $error) {
                // A row that fails unexpectedly stops the batch with its reason, never a silent loop (JB-07; P3).
                Log::error('An import row failed during its commit.', ['import_row_id' => $row->id, 'error' => $error::class]);
                $result = ['state' => 'REFUSED', 'errors' => [['field' => '', 'reason' => 'failed']]];
            }

            if ($result['state'] !== 'COMMITTED') {
                $begun = DB::table('import_rows')->where('id', $row->id)->whereRaw('errors @> ?::jsonb', [json_encode([['reason' => RowRunner::IN_PROGRESS]])])->exists();
                DB::table('import_rows')->where('id', $row->id)->where('validation_state', 'VALID')->update([
                    'errors' => json_encode([...$result['errors'], ...($begun ? [['field' => '', 'reason' => RowRunner::IN_PROGRESS]] : [])], JSON_THROW_ON_ERROR),
                    'updated_at' => CarbonImmutable::now(),
                ]);

                return;
            }
        }

        self::dispatch($this->batchId);
    }

    /** Whether a row of the batch stopped it with its refusal, awaiting an Owner's resume. */
    public static function stopped(int $batchId): bool
    {
        return DB::table('import_rows')->where('import_batch_id', $batchId)->where('validation_state', 'VALID')->whereRaw(RowRunner::REFUSED)->exists();
    }

    /** COMMITTED once no VALID row remains, re-read under the batch's lock. */
    private function finish(ImportBatch $batch): void
    {
        DB::transaction(function () use ($batch): void {
            $locked = ImportBatch::query()->whereKey($batch->id)->lockForUpdate()->first();

            if ($locked === null || $locked->state !== 'SIGNED_OFF' || DB::table('import_rows')->where('import_batch_id', $batch->id)->where('validation_state', 'VALID')->exists()) {
                return;
            }

            $locked->forceFill(['state' => 'COMMITTED', 'lock_version' => $locked->lock_version + 1])->save();
        });
    }
}
