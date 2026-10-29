<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\CostWorld;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-036; QB-14; PFM-20): AX-34's correction cascade, AX-29's charge posting and AX-32's re-spread — retry class
// B commands — in statements proportional to the rows they change, set-based where the posting rules allow: the lock
// derivation reads each guard table once for every key, every CST guard moves in one statement per table (GU-01), and
// each further dependent or line adds a fixed number of statements — its per-key locks (LK-21–LK-23), its own rows and
// the reads of its cumulative targets (DATABASE §6.1–§6.3) —, the same for two and for twelve.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->world = CostWorld::make();
});

/** @return array{statements: int, rows: int, guards: array<string, int>} what the work ran, the cost rows it wrote, its CST guard updates by table */
function costStatements(Closure $work): array
{
    $rows = fn (): int => DB::table('cost_attributions')->count() + DB::table('lot_cost_entries')->count() + DB::table('expenses')->count();
    $before = $rows();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $work();
    $log = DB::getQueryLog();
    DB::disableQueryLog();
    $guards = [];

    foreach ($log as $query) {
        if (preg_match('/^update (lot_cost_balances|purchase_charge_balances|purchase_charge_share_balances) set/', $query['query'], $match) === 1) {
            $guards[$match[1]] = ($guards[$match[1]] ?? 0) + 1;
        }
    }

    return ['statements' => count($log), 'rows' => $rows() - $before, 'guards' => $guards];
}

/** AX-34: a line of 2n units in one lot, one unit dispatched to each of n projects, its price corrected. */
function correctedOver(CostWorld $world, int $n): array
{
    $line = $world->line($world->purchase(), (string) (2 * $n), (200000 * $n).'.00');
    $lot = $world->receive($line, (string) (2 * $n));

    foreach (range(1, $n) as $ignored) {
        $world->dispatch($lot, '1', $world->project(state: 'ACTIVE'));
    }

    return costStatements(fn () => $world->expect($world->correctPrice($line, (220000 * $n).'.00')));
}

/** AX-29: a purchase of n lines, each with its received lot, its charge recorded over them. */
function chargedOver(CostWorld $world, int $n): array
{
    $purchase = $world->purchase();
    $basis = [];

    foreach (range(1, $n) as $number) {
        $line = $world->line($purchase, '4', '400000.00', lineNo: $number);
        $world->receive($line, '2');
        $basis[$line->id] = '400000.00';
    }

    $charge = $world->charge($purchase, (4000 * $n).'.00', $basis);

    return costStatements(fn () => $world->expect($world->recordCharge($charge)));
}

/** AX-32: a line of 2n units with its charge, n + 1 received and n of them dispatched one to a project, its remainder closed. */
function respreadOver(CostWorld $world, int $n): array
{
    $purchase = $world->purchase();
    $line = $world->line($purchase, (string) (2 * $n), (200000 * $n).'.00');
    $world->expect($world->recordCharge($world->charge($purchase, (20000 * $n).'.00', [$line->id => (200000 * $n).'.00'])));
    $lot = $world->receive($line, (string) ($n + 1));

    foreach (range(1, $n) as $ignored) {
        $world->dispatch($lot, '1', $world->project(state: 'ACTIVE'));
    }

    return costStatements(fn () => $world->closeRemainder($line, (string) ($n - 1)));
}

/**
 * The budget: twelve dependents or lines write ten more cost rows than two, each further one adding at most `perRow`
 * statements, and every CST guard table moved in one statement.
 *
 * @param  array{statements: int, rows: int, guards: array<string, int>}  $few
 * @param  array{statements: int, rows: int, guards: array<string, int>}  $many
 */
function withinBudget(array $few, array $many, int $perRow): void
{
    expect($many['rows'] - $few['rows'])->toBe(10)
        ->and($many['statements'] - $few['statements'])->toBeLessThanOrEqual($perRow * 10)
        ->and(array_values(array_unique([...array_values($few['guards']), ...array_values($many['guards'])])))->toBe([1]);
}

it('corrects a price over its lot\'s dependents in statements proportional to them (AX-34; QB-14; PFM-20)', function () {
    // Each further dependent: its COST_CORRECTION row, its project's lock and its revalidation check.
    withinBudget(correctedOver($this->world, 2), correctedOver($this->world, 12), 4);
});

it('posts a charge over its received lines in statements proportional to them (AX-29; QB-14; PFM-20)', function () {
    // Each further line: the locks of its line, guard and lot, its share's guard and CHARGE_SHARE row, its targets' reads.
    withinBudget(chargedOver($this->world, 2), chargedOver($this->world, 12), 18);
});

it('re-spreads a closed remainder over the consumed units in statements proportional to them (AX-32; QB-14; PFM-20)', function () {
    // Each further consumption: its CHARGE_RESPREAD draw and its project's lock.
    withinBudget(respreadOver($this->world, 2), respreadOver($this->world, 12), 2);
});
