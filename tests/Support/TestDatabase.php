<?php

namespace Tests\Support;

use App\Support\Database\DatabaseRole;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * TEST_STRATEGY TS-03 and TASK-003's parallel database strategy: a test that migrates, refreshes,
 * truncates or wraps the database runs on PostgreSQL, never an in-memory substitute, and in the database
 * parallel testing creates for its process — `<database>_test_<process>` —, never in the development
 * database it would otherwise wipe. With the roles of DATABASE §28 (TASK-005), the migration/owner role
 * creates and owns that database, the migrations reach it through that role's connection, and the
 * test itself runs as the application runtime.
 */
final class TestDatabase
{
    /**
     * Creates the process's test database as the migration/owner role, before the framework looks for it
     * as the runtime, which may create no database.
     */
    public static function ensureCreated(string $database): void
    {
        $connection = DB::connection(DatabaseRole::MIGRATION_CONNECTION);
        $owner = $connection->scalar('select pg_get_userbyid(datdba) from pg_database where datname = ?', [$database]);

        if ($owner === null) {
            $connection->statement('create database '.$connection->getQueryGrammar()->wrap($database));
        } elseif ($owner !== $connection->getConfig('username')) {
            throw new LogicException("{$database} belongs to {$owner}, not to the migration role: a volume created before TASK-005 is recreated once with `docker compose down -v` (DEVELOPMENT.md).");
        }

        DB::purge(DatabaseRole::MIGRATION_CONNECTION);
    }

    public static function assertIsolated(string $driver, string $database, string $migrationDatabase): void
    {
        if ($driver !== 'pgsql') {
            throw new LogicException("A database test runs on PostgreSQL, not on {$driver} (TEST_STRATEGY TS-03).");
        }

        if (preg_match('/_test_\d+$/', $database) !== 1) {
            throw new LogicException("A database test runs in a test database of its own: run the suite with --parallel, as scripts/verify.sh does; {$database} is not one.");
        }

        if ($migrationDatabase !== $database) {
            throw new LogicException("The migration connection reaches {$migrationDatabase}, not the test's database {$database}.");
        }
    }
}
