<?php

use App\Modules\CAT\Models\Product;
use App\Modules\PRJ\Models\ProjectLine;
use Database\Factories\PRJ\ProjectLineFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\Refusal;

// TL-05 (TASK-008), C-04 and C-05 under two connections (TEST_STRATEGY §6; DATABASE §5.2): real PostgreSQL
// sessions as the runtime role, the second blocked on the first's uncommitted write to the same guard row
// and re-evaluated on the committed row. The Owner's race (OB §27) dispatches the last unit once; a
// reservation and a dispatch of it, and two reservations of one line, never both commit.

uses(DatabaseTruncation::class);

afterEach(function () {
    // The sessions commit, so the tests after these in this process start from empty tables.
    $this->truncateTablesForAllConnections();
});

/**
 * The first session holds its write uncommitted until the second is blocked on it, then commits; each
 * outcome is `committed` or the refusal's SQLSTATE and message.
 *
 * @param  list<mixed>  $firstBindings
 * @param  list<mixed>  $secondBindings
 * @return array<string, string>
 */
function contend(string $first, array $firstBindings, string $second, array $secondBindings): array
{
    $holder = static function () use ($first, $firstBindings): string {
        DB::beginTransaction();
        DB::affectingStatement($first, $firstBindings);
        Barrier::await('the first holds its guard');
        Sessions::awaitBlocked();
        DB::commit();

        return 'committed';
    };
    $waiter = static function () use ($second, $secondBindings): string {
        Barrier::await('the first holds its guard');

        try {
            DB::affectingStatement($second, $secondBindings);
        } catch (QueryException $exception) {
            return $exception->getCode().' '.$exception->getMessage();
        }

        return 'committed';
    };

    return Sessions::run(['first' => $holder, 'second' => $waiter]);
}

const DISPATCH_ONE = 'update stock_product_balances set on_hand_qty = on_hand_qty - 1, version = version + 1 where product_id = ?';

it('refuses the second of two concurrent writes that would break AVAILABLE ≥ 0 or a reservation bound (C-04; C-05; AX-01–AX-03)', function (Closure $prepare) {
    [$first, $firstBindings, $second, $secondBindings, $sqlState, $constraint] = $prepare();

    $outcomes = contend($first, $firstBindings, $second, $secondBindings);

    expect($outcomes['first'])->toBe('committed')
        ->and($outcomes['second'])->toStartWith($sqlState)->toContain($constraint);
})->with([
    'the Owner\'s race: two dispatches of the last unit (C-04)' => [function () {
        $product = Product::factory()->create()->id;
        DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 1]);

        return [DISPATCH_ONE, [$product], DISPATCH_ONE, [$product], Refusal::CHECK, 'stock_product_balances_c04_available'];
    }],
    'a reservation and a dispatch of the last unit (C-04)' => [function () {
        $product = Product::factory()->create()->id;
        DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 1]);

        return ['update stock_product_balances set reserved_qty = reserved_qty + 1 where product_id = ?', [$product], DISPATCH_ONE, [$product], Refusal::CHECK, 'stock_product_balances_c04_available'];
    }],
    'two reservations of one line\'s last unit of demand (C-05)' => [function () {
        $line = ProjectLine::factory()->create(['entered_qty' => '1.000', 'qty_base' => '1.000']);
        DB::table('project_line_balances')->insert(['project_line_id' => $line->id, 'fulfillment_mode' => 'WAREHOUSE', 'demand_qty_base' => 1]);
        $reserve = 'update project_line_balances set reserved_qty_base = reserved_qty_base + 1 where project_line_id = ?';

        return [$reserve, [$line->id], $reserve, [$line->id], Refusal::CHECK, 'project_line_balances_c30_warehouse'];
    }],
    'two ACTIVE reservations of one line (C-05)' => [function () {
        $line = ProjectLine::factory()->create();
        $key = ProjectLineFactory::keyOf($line->id);
        $reserve = 'insert into reservations (project_line_id, company_id, product_id, quantity_scale, remaining_qty_base) values (?, ?, ?, ?, 1)';
        $bindings = [$line->id, $key['company_id'], $key['product_id'], $key['quantity_scale']];

        return [$reserve, $bindings, $reserve, $bindings, Refusal::UNIQUE, 'reservations_c05_one_active_per_line'];
    }],
]);
