<?php

use App\Support\Database\DatabaseRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;

// TEST_STRATEGY §6 (TASK-003): real PostgreSQL sessions in parallel, held by a barrier at a named point
// and ordered by a lock, proved on a scratch table that each session sees committed. The scratch table is
// created by the migration role and granted to the runtime, as a migration would (TASK-005).

uses(DatabaseTruncation::class);

beforeEach(function () {
    Schema::connection(DatabaseRole::MIGRATION_CONNECTION)->create('harness_counters', function ($table) {
        $table->integer('id')->primary();
        $table->integer('value');
    });
    DB::connection(DatabaseRole::MIGRATION_CONNECTION)->statement('grant select, insert, update on harness_counters to '.DatabaseRole::RUNTIME);

    DB::table('harness_counters')->insert(['id' => 1, 'value' => 0]);
});

afterEach(function () {
    Schema::connection(DatabaseRole::MIGRATION_CONNECTION)->dropIfExists('harness_counters');
});

it('runs each task in a PostgreSQL session of its own on the test database', function () {
    $session = static fn (): array => [DB::scalar('select pg_backend_pid()'), DB::scalar('select current_database()')];

    $results = Sessions::run(['a' => $session, 'b' => $session]);

    expect($results['a'][0])->not->toBe($results['b'][0])
        ->and([$results['a'][1], $results['b'][1]])->each->toBe(DB::connection()->getDatabaseName());
});

it('lets exactly one of two sessions released by a barrier take the row lock', function () {
    $take = static function (): string {
        DB::beginTransaction();
        Barrier::await('before the lock');

        try {
            DB::select('select value from harness_counters where id = 1 for update nowait');
            $outcome = 'locked';
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '55P03') {
                throw $exception;
            }

            $outcome = 'refused';
        }

        Barrier::await('after the attempt');
        DB::rollBack();

        return $outcome;
    };

    $results = Sessions::run(['a' => $take, 'b' => $take]);
    sort($results);

    expect($results)->toBe(['locked', 'refused']);
});

it('orders two writers by the lock, in each order', function (string $first, string $second) {
    $holder = static function (): int {
        DB::beginTransaction();
        $value = (int) DB::scalar('select value from harness_counters where id = 1 for update');
        Barrier::await('the first holds the lock');
        Sessions::awaitBlocked();
        DB::update('update harness_counters set value = ? where id = 1', [$value + 10]);
        DB::commit();

        return $value;
    };
    $waiter = static function (): int {
        Barrier::await('the first holds the lock');
        DB::beginTransaction();
        $value = (int) DB::scalar('select value from harness_counters where id = 1 for update');
        DB::update('update harness_counters set value = ? where id = 1', [$value + 1]);
        DB::commit();

        return $value;
    };

    $results = Sessions::run([$first => $holder, $second => $waiter]);

    expect($results[$first])->toBe(0)
        ->and($results[$second])->toBe(10)
        ->and(DB::table('harness_counters')->value('value'))->toBe(11);
})->with([
    'a holds, b waits' => ['a', 'b'],
    'b holds, a waits' => ['b', 'a'],
]);
