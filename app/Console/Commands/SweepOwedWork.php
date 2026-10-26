<?php

namespace App\Console\Commands;

use App\Support\Jobs\OwedWork;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * JB-09 (CONCURRENCY_IDEMPOTENCY §15), scheduled every minute without overlap: runs every registered sweep of
 * owed work — the export requests and the import batches, registered by their Tasks (DIR-099 reading (1)). One
 * sweep failing never stops the others; a missed run is made up by the next.
 */
class SweepOwedWork extends Command
{
    protected $signature = 'owed-work:sweep';

    protected $description = 'Re-dispatch the export and import work whose dispatch was lost or whose run died';

    public function handle(): int
    {
        $failed = false;

        foreach ($this->laravel->tagged(OwedWork::TAG) as $sweep) {
            if (! $sweep instanceof OwedWork) {
                continue;
            }

            try {
                $this->line($sweep::class.': '.$sweep->sweep());
            } catch (Throwable $error) {
                $failed = true;
                Log::error('A sweep of owed work failed (JB-09).', ['sweep' => $sweep::class, 'error' => class_basename($error)]);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
