<?php

namespace Tests\Support;

use LogicException;

/**
 * TEST_STRATEGY TS-03 and TASK-003's parallel database strategy: a test that migrates, refreshes,
 * truncates or wraps the database runs on PostgreSQL, never an in-memory substitute, and in the database
 * parallel testing creates for its process — `<database>_test_<process>` —, never in the development
 * database it would otherwise wipe.
 */
final class TestDatabase
{
    public static function assertIsolated(string $driver, string $database): void
    {
        if ($driver !== 'pgsql') {
            throw new LogicException("A database test runs on PostgreSQL, not on {$driver} (TEST_STRATEGY TS-03).");
        }

        if (preg_match('/_test_\d+$/', $database) !== 1) {
            throw new LogicException("A database test runs in a test database of its own: run the suite with --parallel, as scripts/verify.sh does; {$database} is not one.");
        }
    }
}
