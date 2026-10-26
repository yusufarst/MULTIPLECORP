<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * JB-08 (CONCURRENCY_IDEMPOTENCY §15; CI-07; HO-27): deletes the expired `command_log` rows in bounded batches,
 * under the DELETE that the `TECH-021` clause of DATABASE §19 grants for expired rows only — the table's trigger
 * refuses any other. After it the per-table command keys still stop a second effect (C-48). Scheduled daily
 * without overlap; a missed run is made up by the next.
 */
class PurgeCommandLog extends Command
{
    public const BATCH = 5000;

    protected $signature = 'command-log:purge';

    protected $description = 'Delete the expired command-log rows in bounded batches';

    public function handle(): int
    {
        $total = 0;

        do {
            $deleted = DB::delete('delete from command_log where id in (select id from command_log where expires_at < now() order by expires_at limit ?)', [self::BATCH]);
            $total += $deleted;
        } while ($deleted === self::BATCH);

        Log::info('Expired command-log rows purged (JB-08).', ['deleted' => $total]);
        $this->line("Deleted: {$total}");

        return self::SUCCESS;
    }
}
