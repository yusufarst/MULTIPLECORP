<?php

namespace App\Console\Commands;

use App\Support\Monitoring\HeartbeatSignals;
use Illuminate\Console\Command;

/**
 * The read-only smoke checks of the business heartbeat (TASK-072; PRQ-03; GAP-013; INFRASTRUCTURE §5 step 6, §6), run by the
 * operator after a deployment: the signals `HeartbeatSignals` reads — the database and its connections, the queue's
 * backlog and its oldest waiting job's age, the failed jobs of the last day, owed and exhausted renditions, each
 * scheduled task's last success and the free disk —, the same ones TASK-075's `ops:heartbeat` alerts on. It reads and
 * never writes (REL-11). Backups are the backup tooling's `backup:status` (TASK-074), run in the operator's container,
 * so they are reported NOT CHECKED here — never a PASS. Exit code 1 when any check fails.
 */
class SmokeCheck extends Command
{
    protected $signature = 'ops:smoke {--queue-max=1000 : the queue backlog past which the check fails} {--oldest-max=15 : the minutes the oldest waiting job may wait} {--disk-min=10 : the free disk, in whole percent, below which the check fails}';

    protected $description = 'Read-only smoke checks of the business heartbeat';

    public function handle(): int
    {
        $count = fn (mixed $value): int => is_numeric($value) ? (int) $value : 0;
        $failed = false;

        foreach (HeartbeatSignals::read(['queue_max' => $count($this->option('queue-max')), 'oldest_minutes' => $count($this->option('oldest-max')), 'disk_min_pct' => $count($this->option('disk-min'))]) as $signal) {
            $failed = $failed || ! $signal['pass'];
            $this->line(($signal['pass'] ? 'PASS' : 'FAIL')." {$signal['signal']} — {$signal['detail']}");
        }

        $this->line('NOT CHECKED backups — `php artisan backup:status` in the operator\'s container (TASK-074)');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
