<?php

namespace App\Console\Commands;

use App\Modules\DOC\Models\DocumentRendition;
use App\Support\Heartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * The read-only smoke checks of the business heartbeat (TASK-072; PRQ-03; GAP-013; INFRASTRUCTURE §5 step 6, §6), run by the
 * operator after a deployment and by monitoring (TASK-075): the database and its connections, the queue's backlog, the
 * failed jobs of the last day, renditions owed past their retry window, each scheduled task's last success and the free
 * disk. It reads and never writes (REL-11). Backup freshness is reported NOT CHECKED until TASK-074's tooling records it —
 * never a PASS. Exit code 1 when any check fails.
 */
class SmokeCheck extends Command
{
    protected $signature = 'ops:smoke {--queue-max=1000 : the queue backlog past which the check fails} {--disk-min=0.1 : the share of free disk below which the check fails}';

    protected $description = 'Read-only smoke checks of the business heartbeat';

    public function handle(): int
    {
        $failed = false;
        $report = function (bool $pass, string $check, string $detail) use (&$failed): void {
            $failed = $failed || ! $pass;
            $this->line(($pass ? 'PASS' : 'FAIL')." {$check} — {$detail}");
        };
        $now = CarbonImmutable::now();

        $count = fn (mixed $value): int => is_numeric($value) ? (int) $value : 0;
        $used = $count(DB::scalar('select count(*) from pg_stat_activity where datname = current_database()'));
        $max = $count(DB::scalar("select setting::int from pg_settings where name = 'max_connections'"));
        $report($max > 0 && $used <= (int) floor($max * 0.8), 'database', "connections {$used} of {$max}");

        $backlog = Queue::size();
        $report($backlog <= (int) $this->option('queue-max'), 'queue', "{$backlog} jobs waiting");

        $recentFailures = DB::table('failed_jobs')->where('failed_at', '>=', $now->subDay())->count();
        $report($recentFailures === 0, 'failed jobs', "{$recentFailures} in the last 24 hours");

        $owed = DocumentRendition::query()
            ->whereIn('state', ['PENDING', 'RENDERING'])
            ->where('recorded_at', '<=', $now->subMinutes(15))
            ->whereRaw('attempt_no = (select max(x.attempt_no) from document_renditions x where x.document_version_id = document_renditions.document_version_id)')
            ->count();
        $report($owed === 0, 'renditions', "{$owed} owed for more than 15 minutes");

        foreach (Heartbeat::TASKS as $task => $minutes) {
            $last = Heartbeat::last($task);
            $report($last !== null && $last->greaterThanOrEqualTo($now->subMinutes($minutes)), "scheduler {$task}", $last === null ? 'no success recorded' : 'last success '.$last->toIso8601String());
        }

        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $share = $free !== false && $total !== false && $total > 0 ? $free / $total : 0.0;
        $report($share >= (float) $this->option('disk-min'), 'disk', sprintf('%.0f%% free', $share * 100));

        $this->line('NOT CHECKED backups — recorded by TASK-074\'s tooling');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
