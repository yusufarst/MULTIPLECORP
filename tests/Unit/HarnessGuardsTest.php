<?php

use PHPUnit\Framework\AssertionFailedError;
use Tests\Support\GuardReconciliation;
use Tests\Support\TestDatabase;

// TEST_STRATEGY TS-03 and TS-11: a database test runs on PostgreSQL in its own test database, migrated
// there through the migration role's connection (TASK-005), and a seeded drift between a guard and its
// facts fails the reconciliation that follows each integration test.

it('accepts PostgreSQL in a per-process test database', function () {
    TestDatabase::assertIsolated('pgsql', 'multiplecorp_test_3', 'multiplecorp_test_3');

    expect(true)->toBeTrue();
});

it('refuses an in-memory substitute, the development database and migrations aimed elsewhere', function (string $driver, string $database, string $migrationDatabase) {
    expect(fn () => TestDatabase::assertIsolated($driver, $database, $migrationDatabase))->toThrow(LogicException::class);
})->with([
    'SQLite in memory' => ['sqlite', ':memory:', ':memory:'],
    'the development database' => ['pgsql', 'multiplecorp', 'multiplecorp'],
    'a look-alike name' => ['pgsql', 'multiplecorp_test', 'multiplecorp_test'],
    'migrations aimed at the development database' => ['pgsql', 'multiplecorp_test_3', 'multiplecorp'],
]);

it('reports a seeded drift and nothing once the guards agree', function () {
    $drift = ['stock_balances: product P-1 on hand 9, facts 10'];

    GuardReconciliation::register('seeded', function () use (&$drift): array {
        return $drift;
    });

    try {
        expect(GuardReconciliation::differences())->toBe(['seeded: stock_balances: product P-1 on hand 9, facts 10'])
            ->and(fn () => GuardReconciliation::assertNone())->toThrow(AssertionFailedError::class);

        $drift = [];

        GuardReconciliation::assertNone();
    } finally {
        GuardReconciliation::forget('seeded');
    }

    expect(GuardReconciliation::differences())->toBe([]);
});
