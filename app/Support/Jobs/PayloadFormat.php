<?php

namespace App\Support\Jobs;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * GAP-012's payload versioning (CONCURRENCY_IDEMPOTENCY §15): a payload newer than the worker knows goes back to the
 * queue with a delay as a fresh job, its attempts unspent — a newer worker will take it once deployed —; the current and the previous format run; an
 * older one fails, because no release reads it any more (HO-31).
 */
final class PayloadFormat
{
    public const RELEASE_DELAY_SECONDS = 60;

    public function handle(PlatformJob $job, Closure $next): mixed
    {
        $known = $job->knownFormat();

        if ($job->format > $known) {
            Log::warning('A job payload is newer than this worker knows; queued again (GAP-012).', ['job' => $job::class, 'format' => $job->format, 'known' => $known]);
            // A fresh copy, delayed, so the waits never spend the job's attempts; the copy delivered is removed.
            dispatch((clone $job)->delay(self::RELEASE_DELAY_SECONDS));
            $job->delete();

            return null;
        }

        if ($job->format < $known - 1) {
            $job->fail(new \RuntimeException("The payload format {$job->format} of ".$job::class." is no longer read (known {$known}; GAP-012)."));

            return null;
        }

        return $next($job);
    }
}
