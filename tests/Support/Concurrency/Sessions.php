<?php

namespace Tests\Support\Concurrency;

use App\Support\SecurityLog\SecurityEventRecorder;
use Closure;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Parallel PostgreSQL sessions for the race tests of TEST_STRATEGY §6 (TASK-003): each task runs in a
 * PHP process of its own, on its own connection to the test's database, so that locks, waits and commit
 * order are PostgreSQL's. Barrier::await() and awaitBlocked() set the interleaving. A session sees only
 * committed data, so a race test uses DatabaseTruncation, never a test-wide transaction.
 */
final class Sessions
{
    /**
     * @param  array<string, Closure(): mixed>  $tasks  static closures, one per session, using neither $this nor self
     * @return array<string, mixed> each task's result
     */
    public static function run(array $tasks, int $timeoutSeconds = 60): array
    {
        $connection = DB::getDefaultConnection();
        $database = DB::connection()->getDatabaseName();
        $run = Barrier::open(count($tasks));

        try {
            $sessions = [];

            foreach ($tasks as $name => $task) {
                // A closure written in a test keeps its test class as scope, which no other process has.
                $task = Closure::bind($task, null, null) ?? $task;

                $sessions[$name] = static function () use ($task, $connection, $database, $run): mixed {
                    config(["database.connections.{$connection}.database" => $database]);
                    DB::purge($connection);
                    config(['database.connections.'.SecurityEventRecorder::CONNECTION.'.database' => $database]);
                    DB::purge(SecurityEventRecorder::CONNECTION);
                    Barrier::join($run);

                    return $task();
                };
            }

            return Concurrency::driver('process')->run($sessions, $timeoutSeconds);
        } finally {
            Barrier::close($run);
        }
    }

    /**
     * Waits until $count sessions of this database are blocked on a lock.
     */
    public static function awaitBlocked(int $count = 1, int $timeoutSeconds = 30): void
    {
        $deadline = hrtime(true) + $timeoutSeconds * 1_000_000_000;
        $blocked = "select count(*) from pg_stat_activity where datname = current_database() and wait_event_type = 'Lock'";

        while (true) {
            // Inside a transaction PostgreSQL shows the activity it read first until the snapshot is cleared.
            DB::select('select pg_stat_clear_snapshot()');

            if ((int) DB::scalar($blocked) >= $count) {
                return;
            }

            if (hrtime(true) > $deadline) {
                throw new RuntimeException("{$count} session(s) were not blocked on a lock within {$timeoutSeconds} s.");
            }

            usleep(5_000);
        }
    }
}
