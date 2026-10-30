<?php

use App\Modules\CAT\Models\Product;
use App\Modules\INV\Models\StockMovementEntry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Support\DatabaseTruncation;
use Tests\Support\GuardReconciliation;

// TL-05 (TASK-013; GAP-031; ARCHITECTURE §7; DATABASE §3.1): `ReconcileGuards` finds every seeded difference
// between a guard and its facts — a wrong row, a missing row, a row whose facts are gone — repairing none and
// auditing its run, and TS-11 sees the same differences; `GuardMaintenance`, the operator's console command under
// the administration role, rebuilds them with an audit event per row, its reason, reference and operator, and
// never invents a column its family does not recompute. Both run in their own transactions, so the tests commit.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

/** @return array<string, mixed> */
function storedGuard(string $table, string $column, int|string $key): array
{
    return (array) DB::table($table)->where($column, $key)->first();
}

it('reports each seeded difference and repairs nothing, then the operator rebuilds them with one audit per row', function () {
    $entry = StockMovementEntry::factory()->create(['qty_delta_base' => '3.000']);
    $stale = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $stale, 'on_hand_qty' => 5]);

    expect(Artisan::call('guards:reconcile', ['--operator' => 'Tester']))->toBe(1)
        ->and(Artisan::output())->toContain('4 difference(s) found; nothing repaired.')
        ->and(GuardReconciliation::familyDifferences())->toHaveCount(4)
        ->and(storedGuard('stock_product_balances', 'product_id', $stale)['on_hand_qty'])->toBe('5.000')
        ->and(DB::table('stock_lot_balances')->count())->toBe(0);

    $run = DB::table('audit_events')->where('action', 'guards.reconcile')->sole();
    expect($run->authority_class)->toBe('SYSTEM')
        ->and($run->actor_user_id)->toBeNull()
        ->and(json_decode($run->metadata, true))->toMatchArray(['differences' => 4, 'operator' => ['host_account' => get_current_user(), 'stated_name' => 'Tester']]);

    // The lot's cost row is missing, and its remaining cost is no formula of this family: nothing is rebuilt.
    expect(Artisan::call('guards:maintain', ['family' => 'lot_cost_balances', '--reason' => 'restore check', '--reference' => 'RESTORE-1']))->toBe(1)
        ->and(DB::table('lot_cost_balances')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'guards.maintain')->count())->toBe(0);
    DB::table('lot_cost_balances')->insert(['lot_id' => $entry->lot_id]);

    foreach (['stock_product_balances', 'stock_lot_balances', 'lot_cost_balances'] as $family) {
        expect(Artisan::call('guards:maintain', ['family' => $family, '--reason' => 'verified defect', '--reference' => 'DEFECT-7', '--operator' => 'Tester']))->toBe(0);
    }

    expect(storedGuard('stock_product_balances', 'product_id', $stale)['on_hand_qty'])->toBe('0.000')
        ->and(storedGuard('stock_product_balances', 'product_id', $entry->product_id)['on_hand_qty'])->toBe('3.000')
        ->and(storedGuard('stock_lot_balances', 'lot_id', $entry->lot_id))->toMatchArray(['product_id' => $entry->product_id, 'location_id' => $entry->location_id, 'qty' => '3.000'])
        ->and(storedGuard('lot_cost_balances', 'lot_id', $entry->lot_id)['remaining_qty_base'])->toBe('3.000');

    $repairs = DB::table('audit_events')->where('action', 'guards.maintain')->orderBy('id')->get();
    expect($repairs)->toHaveCount(4)
        ->and($repairs->pluck('entity_type')->all())->toBe(['stock_product_balances', 'stock_product_balances', 'stock_lot_balances', 'lot_cost_balances'])
        ->and($repairs->pluck('reason')->unique()->all())->toBe(['verified defect']);
    $stored = $repairs->first(fn ($event) => json_decode($event->metadata, true)['key'] === ['product_id' => $stale]);
    expect(json_decode($stored->before, true))->toBe(['on_hand_qty' => '5.000'])
        ->and(json_decode($stored->after, true))->toBe(['on_hand_qty' => '0.000'])
        ->and(json_decode($stored->metadata, true))->toMatchArray(['reference' => 'DEFECT-7', 'operator' => ['host_account' => get_current_user(), 'stated_name' => 'Tester']]);

    expect(Artisan::call('guards:reconcile'))->toBe(0)
        ->and(GuardReconciliation::familyDifferences())->toBe([]);
});

it('fails an integration test that leaves a guard differing from its facts, except where TS-11 does not reconcile (TS-11)', function () {
    $stale = Product::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $stale, 'on_hand_qty' => 2]);

    expect(fn () => GuardReconciliation::assertNone(__FILE__, $this))->toThrow(AssertionFailedError::class, 'ReconcileGuards: {"family":"stock_product_balances"')
        ->and(fn () => GuardReconciliation::assertNone(str_replace('/Guards/', '/Schema/', __FILE__), $this))->not->toThrow(AssertionFailedError::class)
        ->and(fn () => GuardReconciliation::assertNone(__FILE__))->not->toThrow(AssertionFailedError::class);

    DB::table('stock_product_balances')->where('product_id', $stale)->update(['on_hand_qty' => 0]);
});

it('refuses a rebuild without a registered family, a reason and a reference', function (array $arguments) {
    expect(Artisan::call('guards:maintain', $arguments))->toBe(2)
        ->and(DB::table('audit_events')->count())->toBe(0);
})->with([
    'an unregistered family' => [['family' => 'no_such_guard', '--reason' => 'r', '--reference' => 'x']],
    'no reason' => [['family' => 'stock_lot_balances', '--reference' => 'x']],
    'no reference' => [['family' => 'stock_lot_balances', '--reason' => 'r', '--reference' => ' ']],
]);

it('runs from the console only, never routed, and GuardMaintenance never scheduled (ARCHITECTURE §7; GU-07)', function () {
    $scheduled = collect(app(Schedule::class)->events())->map(fn ($event) => (string) $event->command);
    $routed = collect(app('router')->getRoutes()->getRoutes())->map(fn ($route) => $route->getActionName());

    // ReconcileGuards' nightly run is JB-06's, registered with the jobs (TASK-022); a repair is never a job.
    expect($scheduled->filter(fn (string $command) => str_contains($command, 'guards:maintain'))->all())->toBe([])
        ->and($routed->filter(fn (string $action) => str_contains($action, 'Guard'))->all())->toBe([])
        ->and(array_keys(Artisan::all()))->toContain('guards:reconcile', 'guards:maintain');
});
