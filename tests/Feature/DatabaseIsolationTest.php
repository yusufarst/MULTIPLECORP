<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// TL-04 against the stack (TASK-003): a database test runs on the stack's PostgreSQL, in the test
// database parallel testing creates for its process (TEST_STRATEGY TS-03).

uses(RefreshDatabase::class);

it('runs on PostgreSQL in a test database of its own process', function () {
    $database = DB::connection()->getDatabaseName();

    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and($database)->toMatch('/_test_\d+$/')
        ->and(DB::scalar('select current_database()'))->toBe($database)
        ->and(DB::table('migrations')->count())->toBe(0);
});
