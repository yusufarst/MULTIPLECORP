<?php

namespace App\Console\Commands;

use App\Modules\DOC\Jobs\RenditionSweeper;
use Illuminate\Console\Command;

/** JB-02, run by the scheduler in its own process every minute without overlap (CONCURRENCY_IDEMPOTENCY §15). */
class SweepRenditions extends Command
{
    protected $signature = 'renditions:sweep';

    protected $description = 'Re-dispatch the rendering of every document version whose latest attempt is owed';

    public function handle(): int
    {
        $sweeper = new RenditionSweeper;
        $this->laravel->call([$sweeper, 'handle']);
        $this->line('Re-dispatched: '.$sweeper->redispatched);

        return self::SUCCESS;
    }
}
