<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The scheduler's side of the business heartbeat (TASK-072; INFRASTRUCTURE §6; HO-25): each scheduled task records its
 * last successful run in the store, and the read-only smoke check reads it back — a task that stopped succeeding is seen
 * by its age, never by a log line someone has to find. Alerting on it is TASK-075's.
 */
final class Heartbeat
{
    /** Each scheduled task with the age, in minutes, past which its last success is stale. */
    public const TASKS = [
        'renditions:sweep' => 5,
        'owed-work:sweep' => 5,
        'guards:reconcile' => 26 * 60,
        'command-log:purge' => 26 * 60,
        'queue:prune-failed' => 26 * 60,
        'credential-links:purge' => 26 * 60,
    ];

    /** The callback a scheduled task runs on success. */
    public static function stamp(string $task): Closure
    {
        return function () use ($task): void {
            Cache::forever(self::key($task), CarbonImmutable::now()->toIso8601String());
        };
    }

    /** The task's last success, or null when none is recorded. */
    public static function last(string $task): ?CarbonImmutable
    {
        $at = Cache::get(self::key($task));

        return is_string($at) ? CarbonImmutable::parse($at) : null;
    }

    private static function key(string $task): string
    {
        return "heartbeat:{$task}";
    }
}
