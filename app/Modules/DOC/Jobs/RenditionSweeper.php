<?php

namespace App\Modules\DOC\Jobs;

use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Services\Renditions;
use App\Support\Jobs\PlatformJob;
use Carbon\CarbonImmutable;

/**
 * JB-02 `RenditionSweeper` (CONCURRENCY_IDEMPOTENCY §15; ARCHITECTURE §11; GAP-007), run by the scheduler every
 * minute without overlap: re-dispatches JB-01 for a version's latest attempt when it is PENDING longer than 2
 * minutes, RENDERING longer than 10, or FAILED longer than 1, 5, 15 and 60 minutes by attempt number, up to five
 * attempts — age read from the attempt's `recorded_at`. It only re-queues and changes no row; JB-01's unique-job
 * lock drops a dispatch while that render runs, so a crash between commit and dispatch loses nothing.
 */
final class RenditionSweeper extends PlatformJob
{
    public int $redispatched = 0;

    public function handle(): void
    {
        $now = CarbonImmutable::now();
        $owed = DocumentRendition::query()
            ->whereIn('state', ['PENDING', 'RENDERING', 'FAILED'])
            ->where('recorded_at', '<=', $now->subMinute())
            ->where(fn ($query) => $query->where('state', '<>', 'FAILED')->orWhere('attempt_no', '<', Renditions::attempts()))
            ->whereRaw('attempt_no = (select max(x.attempt_no) from document_renditions x where x.document_version_id = document_renditions.document_version_id)')
            ->orderBy('recorded_at')
            ->get();

        foreach ($owed as $attempt) {
            if (Renditions::isDue($attempt, $now)) {
                RenderDocument::dispatch($attempt->id);
                $this->redispatched++;
            }
        }
    }
}
