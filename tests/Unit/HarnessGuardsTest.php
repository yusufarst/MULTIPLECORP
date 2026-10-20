<?php

use PHPUnit\Framework\AssertionFailedError;
use Tests\Support\GuardReconciliation;
use Tests\Support\TestDatabase;

// TEST_STRATEGY TS-03 and TS-11: a database test runs on PostgreSQL in its own test database, and a
// seeded drift between a guard and its facts fails the reconciliation that follows each integration test.

it('accepts PostgreSQL in a per-process test database', function () {
    TestDatabase::assertIsolated('pgsql', 'multiplecorp_test_3');

    expect(true)->toBeTrue();
});

it('refuses an in-memory substitute and the development database', function (string $driver, string $database) {
    expect(fn () => TestDatabase::assertIsolated($driver, $database))->toThrow(LogicException::class);
})->with([
    'SQLite in memory' => ['sqlite', ':memory:'],
    'the development database' => ['pgsql', 'multiplecorp'],
    'a look-alike name' => ['pgsql', 'multiplecorp_test'],
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
