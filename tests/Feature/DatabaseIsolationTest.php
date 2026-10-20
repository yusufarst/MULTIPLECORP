<?php

use App\Support\Database\DatabaseRole;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;

// TL-04 against the stack (TASK-003): a database test runs on the stack's PostgreSQL, in the test
// database parallel testing creates for its process (TEST_STRATEGY TS-03) — migrated there by the
// migration/owner role and used by the application runtime (TASK-005; DATABASE §28).

uses(RefreshDatabase::class);

it('runs on PostgreSQL in a test database of its own process', function () {
    $database = DB::connection()->getDatabaseName();

    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and($database)->toMatch('/_test_\d+$/')
        ->and(DB::scalar('select current_database()'))->toBe($database);
});

it('is migrated by the migration role and used by the runtime', function () {
    $migration = DB::connection(DatabaseRole::MIGRATION_CONNECTION);
    $migrations = glob(database_path('migrations/*.php')) ?: [];

    expect($migration->getDatabaseName())->toBe(DB::connection()->getDatabaseName())
        ->and($migration->table('migrations')->count())->toBe(count($migrations))
        ->and(DB::scalar('select pg_get_userbyid(datdba) from pg_database where datname = current_database()'))->toBe($migration->getConfig('username'))
        ->and(DB::scalar('select pg_has_role(current_user, ?, ?)', [DatabaseRole::RUNTIME, 'member']))->toBeTrue()
        ->and(DB::scalar('select current_user'))->not->toBe($migration->getConfig('username'));
});
