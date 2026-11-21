<?php

namespace App\Support\Monitoring;

use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Services\Renditions;
use App\Support\Heartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * The business heartbeat of INFRASTRUCTURE §6 (GAP-013; HO-25; PRQ-03), read-only and off the request path: database
 * connections, the queue's backlog and its oldest job's age, failed jobs, owed and exhausted renditions, each scheduled
 * task's last success — `ReconcileGuards` and the purges a day apart —, and free disk against `<DISK_WARN_PCT>` and
 * `<DISK_CRIT_PCT>`. TASK-072's smoke checks print it; TASK-075's `ops:heartbeat` raises each failing signal as an alert.
 * Backups are read by the backup tooling's own status, in the operator's container (BK-10).
 */
final class HeartbeatSignals
{
    /**
     * @param  array{queue_max?: int, oldest_minutes?: int, owed_minutes?: int, disk_min_pct?: int, disk_warn_pct?: int, disk_critical_pct?: int}  $limits
     * @return list<array{signal: string, pass: bool, detail: string}>
     */
    public static function read(array $limits = []): array
    {
        $now = CarbonImmutable::now();
        $count = fn (mixed $value): int => is_numeric($value) ? (int) $value : 0;
        $signals = [];
        $add = function (string $signal, bool $pass, string $detail) use (&$signals): void {
            $signals[] = ['signal' => $signal, 'pass' => $pass, 'detail' => $detail];
        };

        $used = $count(DB::scalar('select count(*) from pg_stat_activity where datname = current_database()'));
        $max = $count(DB::scalar("select setting::int from pg_settings where name = 'max_connections'"));
        $add('database', $max > 0 && $used <= (int) floor($max * 0.8), "connections {$used} of {$max}");

        $backlog = Queue::size();
        $add('queue', $backlog <= ($limits['queue_max'] ?? 1000), "{$backlog} jobs waiting");

        // The oldest waiting job's age: a stalled worker shows here before the backlog grows.
        $oldest = Queue::creationTimeOfOldestPendingJob();
        $waited = $oldest === null ? 0 : max(0, $now->getTimestamp() - $oldest);
        $add('oldest job', $waited <= ($limits['oldest_minutes'] ?? 15) * 60, $oldest === null ? 'no job waiting' : intdiv($waited, 60).' minutes waiting');

        $recentFailures = DB::table('failed_jobs')->where('failed_at', '>=', $now->subDay())->count();
        $add('failed jobs', $recentFailures === 0, "{$recentFailures} in the last 24 hours");

        $latest = 'attempt_no = (select max(x.attempt_no) from document_renditions x where x.document_version_id = document_renditions.document_version_id)';
        $owed = DocumentRendition::query()->whereIn('state', ['PENDING', 'RENDERING'])->where('recorded_at', '<=', $now->subMinutes($limits['owed_minutes'] ?? 15))->whereRaw($latest)->count();
        $add('renditions', $owed === 0, "{$owed} owed for more than ".($limits['owed_minutes'] ?? 15).' minutes');

        // A rendition FAILED after its five attempts waits for the operator (JB-01; GAP-007).
        $exhausted = DocumentRendition::query()->where('state', 'FAILED')->where('attempt_no', '>=', Renditions::attempts())->whereRaw($latest)->count();
        $add('renditions exhausted', $exhausted === 0, "{$exhausted} failed after their last attempt");

        foreach (Heartbeat::TASKS as $task => $minutes) {
            $last = Heartbeat::last($task);
            $add("scheduler {$task}", $last !== null && $last->greaterThanOrEqualTo($now->subMinutes($minutes)), $last === null ? 'no success recorded' : 'last success '.$last->toIso8601String());
        }

        // Whole percent, rounded down, so a fraction never passes a threshold (ARCHITECTURE §19: no float cast).
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $freePct = $free !== false && $total !== false && $total > 0 ? (int) floor($free * 100 / $total) : 0;
        $usedPct = 100 - $freePct;
        $add('disk', $freePct >= ($limits['disk_min_pct'] ?? 0)
            && $usedPct < ($limits['disk_warn_pct'] ?? 101), "{$freePct}% free".($usedPct >= ($limits['disk_critical_pct'] ?? 101) ? ', at its critical level' : ($usedPct >= ($limits['disk_warn_pct'] ?? 101) ? ', at its warning level' : '')));

        return $signals;
    }

    /**
     * The configured alert limits (config/monitoring.php).
     *
     * @return array{queue_max: int, oldest_minutes: int, owed_minutes: int, disk_warn_pct: int, disk_critical_pct: int}
     */
    public static function configured(): array
    {
        return [
            'queue_max' => config()->integer('monitoring.heartbeat.queue_max'),
            'oldest_minutes' => config()->integer('monitoring.heartbeat.oldest_minutes'),
            'owed_minutes' => config()->integer('monitoring.heartbeat.owed_minutes'),
            'disk_warn_pct' => config()->integer('monitoring.heartbeat.disk_warn_pct'),
            'disk_critical_pct' => config()->integer('monitoring.heartbeat.disk_critical_pct'),
        ];
    }
}
