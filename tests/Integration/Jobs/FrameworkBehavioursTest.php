<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Predis\Connection\Resource\Exception\StreamInitException;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-022; TEST_STRATEGY §6; CONCURRENCY_IDEMPOTENCY HO-20; criterion 7): the framework behaviours the jobs
// rely on, each proved on this framework version against the configured store, Valkey (INFRASTRUCTURE §2), so a
// changed version fails here — a cache lock taken inside a database transaction is the store's at once and survives
// the transaction's rollback, the database being no part of it; the scheduler with its store down cannot take a task's
// overlap lock, so the run stops and the task does not run, the next minute running it once the store is back — a
// daily task's lost minute is HO-25's alert. HO-20's
// database-store case is not applicable: no database cache store is configured. No job uses the framework's
// batching (the architecture scan `job-batching`).

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'redis']);
    Cache::forgetDriver('redis');
});

/** The store's clients rebuilt from the current configuration: the Redis manager keeps the one it was made with. */
function rebuildStore(): void
{
    app()->forgetInstance('redis');
    app()->forgetInstance('cache.store');
    Redis::clearResolvedInstance('redis');
    Cache::forgetDriver('redis');
}

afterEach(function () {
    Cache::store('redis')->lock('ho20-lock')->forceRelease();
});

it('takes a cache lock on Valkey inside a transaction, held at once and kept after a rollback (HO-20)', function () {
    try {
        DB::transaction(function (): void {
            expect(Cache::store('redis')->lock('ho20-lock', 30)->get())->toBeTrue()
                // Another owner sees it held before the transaction ends: the store, not the database, holds it.
                ->and(Cache::store('redis')->lock('ho20-lock', 30)->get())->toBeFalse();

            throw new RuntimeException('roll back');
        });
    } catch (RuntimeException) {
    }

    expect(Cache::store('redis')->lock('ho20-lock', 30)->get())->toBeFalse()
        ->and(config('cache.stores.redis.driver'))->toBe('redis')
        ->and(collect(config('cache.stores'))->where('driver', 'database')->keys()->filter(fn ($store) => $store === config('cache.default'))->all())->toBe([]);
});

it('declares every scheduled task overlap-protected with an expiry (CONCURRENCY_IDEMPOTENCY §15)', function () {
    Artisan::call('schedule:list');
    $events = app(Schedule::class)->events();
    $commands = implode("\n", array_map(fn ($event): string => (string) $event->command, $events));

    foreach (['renditions:sweep', 'owed-work:sweep', 'guards:reconcile', 'command-log:purge', 'queue:prune-failed', 'credential-links:purge'] as $task) {
        expect($commands)->toContain($task);
    }

    foreach ($events as $event) {
        expect($event->withoutOverlapping)->toBeTrue()
            ->and($event->expiresAt)->toBeGreaterThan(0);
    }
});

it('runs no overlap-protected task with its store down, and runs it the next minute once the store is back (HO-20)', function () {
    $ran = 0;
    $this->app->instance(Schedule::class, $schedule = new Schedule);
    $schedule->call(function () use (&$ran): void {
        $ran++;
    })->name('ho20-probe')->everyMinute()->withoutOverlapping(10);

    // The store down: its connections point at a closed port, with no retry.
    $working = config('database.redis');
    config(['database.redis.default.port' => 1, 'database.redis.cache.port' => 1, 'database.redis.default.max_retries' => 0, 'database.redis.cache.max_retries' => 0]);
    rebuildStore();

    // On this version the run stops at the overlap lock it cannot take: the task does not run, the minute is lost.
    expect(fn () => Artisan::call('schedule:run'))->toThrow(StreamInitException::class)
        ->and($ran)->toBe(0);

    // The next minute, the store back: the task runs.
    config(['database.redis' => $working]);
    rebuildStore();
    $this->travel(1)->minutes();

    expect(Artisan::call('schedule:run'))->toBe(0)
        ->and($ran)->toBe(1);
});
