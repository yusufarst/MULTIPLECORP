<?php

use App\Modules\PRJ\Models\Project;
use App\Modules\PUR\Models\PurchaseLine;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\CostWorld;
use Tests\Support\DatabaseTruncation;

// TL-05 (TASK-036; C-26; AX-34; M-07; criteria 11, 12) on real PostgreSQL sessions: two draws of one lot serialize at its
// cost guard — the second waits and draws from what the first left, each unit's cost drawn once and the lot reaching
// exactly zero with its last unit —; and a price correction racing a dispatch of its lot ends with Σ attributed = the
// corrected cost whether the dispatch committed before the correction derived its locks, between that derivation and
// their acquisition — a restart with the dispatch's project —, or after the correction's locks. Each session carries
// plain ids and loads its models once it has joined the test's database.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->world = CostWorld::make();
    $this->project = $this->world->project(state: 'ACTIVE');
});

/** @return array{0: list<string>, 1: string} the consumption's cost rows and the lot's remaining cost */
function racedCost(int $consumption, int $lot): array
{
    return [CostWorld::rows($consumption), (string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost')];
}

it('serializes two draws of one lot at its cost guard: each unit drawn once, the lot exactly zero with its last unit (C-26; criterion 11)', function () {
    [$ids, $project] = [$this->world->ids(), $this->project->id];
    $lot = $this->world->receive($this->world->line($this->world->purchase(), '3', '100000.00'), '3');

    $results = Sessions::run([
        'first' => static fn (): int => DB::transaction(static function () use ($ids, $lot, $project): int {
            $consumption = CostWorld::resume($ids)->dispatch($lot, '1', Project::query()->findOrFail($project));
            Barrier::await('the first draw holds the lot');
            Sessions::awaitBlocked();

            return $consumption;
        }),
        'second' => static function () use ($ids, $lot, $project): int {
            Barrier::await('the first draw holds the lot');

            return CostWorld::resume($ids)->dispatch($lot, '1', Project::query()->findOrFail($project));
        },
    ]);
    $last = $this->world->dispatch($lot, '1', $this->project);

    // 100,000 over 3: the first 33,333; the second, waiting, half of the 66,667 left as 33,334; the last unit the rest.
    expect(CostWorld::rows($results['first']))->toBe(['HPP INITIAL 33333.00'])
        ->and(CostWorld::rows($results['second']))->toBe(['HPP INITIAL 33334.00'])
        ->and(CostWorld::rows($last))->toBe(['HPP INITIAL 33333.00'])
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->first(['remaining_qty_base', 'remaining_cost']))->toMatchObject(['remaining_qty_base' => '0.000', 'remaining_cost' => '0.00'])
        ->and(CostWorld::differences())->toBe([]);
});

it('corrects a dispatch committed before the correction derived its locks (M-07 before step 1; criterion 12)', function () {
    $line = $this->world->line($this->world->purchase(), '12', '120000.00');
    $lot = $this->world->receive($line, '12');
    $dispatch = $this->world->dispatch($lot, '2', $this->project);
    $this->world->attempts = 0;
    $this->world->expect($this->world->correctPrice($line, '132000.00'));

    expect(racedCost($dispatch, $lot))->toBe([['HPP INITIAL 20000.00', 'HPP COST_CORRECTION 2000.00'], '110000.00'])
        ->and($this->world->attempts)->toBe(1)
        ->and(CostWorld::differences())->toBe([]);
});

it('restarts a correction whose lot took a dispatch between its derivation and its locks, with that dispatch\'s project (M-07 between steps 1 and 2; criterion 12)', function () {
    [$ids, $project] = [$this->world->ids(), $this->project->id];
    $line = $this->world->line($this->world->purchase(), '12', '120000.00');
    $lot = $this->world->receive($line, '12');
    $lineId = $line->id;

    $results = Sessions::run([
        'correction' => static function () use ($ids, $lineId): array {
            $world = CostWorld::resume($ids);
            $outcome = $world->correctPrice(PurchaseLine::query()->findOrFail($lineId), '132000.00', static function (): void {
                Barrier::await('the correction derived its locks');
                Barrier::await('the dispatch committed');
            });

            return [$outcome->kind, $world->attempts];
        },
        'dispatch' => static function () use ($ids, $lot, $project): int {
            Barrier::await('the correction derived its locks');
            $consumption = CostWorld::resume($ids)->dispatch($lot, '2', Project::query()->findOrFail($project));
            Barrier::await('the dispatch committed');

            return $consumption;
        },
    ]);

    // The dispatch drew the old 20,000 and the restarted correction reached it: Σ attributed + the lot = 132,000.
    expect($results['correction'])->toBe(['COMMITTED', 2])
        ->and(racedCost($results['dispatch'], $lot))->toBe([['HPP INITIAL 20000.00', 'HPP COST_CORRECTION 2000.00'], '110000.00'])
        ->and(CostWorld::differences())->toBe([]);
});

it('lets a dispatch waiting on the correction\'s locks draw the corrected cost (M-07 after the cascade\'s locks; criterion 12)', function () {
    [$ids, $project] = [$this->world->ids(), $this->project->id];
    $line = $this->world->line($this->world->purchase(), '12', '120000.00');
    $lot = $this->world->receive($line, '12');
    $lineId = $line->id;

    $results = Sessions::run([
        'correction' => static fn (): string => DB::transaction(static function () use ($ids, $lineId): string {
            $outcome = CostWorld::resume($ids)->correctPrice(PurchaseLine::query()->findOrFail($lineId), '132000.00');
            Barrier::await('the correction holds the lot');
            Sessions::awaitBlocked();

            return $outcome->kind;
        }),
        'dispatch' => static function () use ($ids, $lot, $project): int {
            Barrier::await('the correction holds the lot');

            return CostWorld::resume($ids)->dispatch($lot, '2', Project::query()->findOrFail($project));
        },
    ]);

    expect($results['correction'])->toBe('COMMITTED')
        ->and(racedCost($results['dispatch'], $lot))->toBe([['HPP INITIAL 22000.00'], '110000.00'])
        ->and(CostWorld::differences())->toBe([]);
});
