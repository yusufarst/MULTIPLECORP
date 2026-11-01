<?php

use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\ReservationWorld;
use Tests\Support\StockWorld;

// TL-05 (TASK-040; NX-11; CM-28; C-55; C-27; C-06; FU-03; SF-LOSS; DATABASE §5.3): ROUTE-212 on fixture movements of each reversible type — a condition change, an adjustment decrease with its loss, a count's loss, a surplus attribution and a pending case's recognition —, each re-run end to end by the Task that records it; two reversals of one movement racing; the reconciliation suite after each.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, StockWorld::CAPABILITIES)->id;
    $goods = Projects::product();
    $this->product = $goods['product']->id;
    $this->unit = $goods['base']->id;
    $this->rack = ReceivingWorld::rack();
    $this->lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
});

function reversalDifferences(): array
{
    $found = [];

    foreach (['stock_product_balances', 'stock_scope_balances', 'stock_lot_balances', 'stock_reversal_balances', 'stock_consumption_balances', 'lot_cost_balances'] as $family) {
        $named = Families::named($family);

        if ($named !== null) {
            $found = [...$found, ...(new Reconciler)->differences(DB::connection(), $named)];
        }
    }

    return $found;
}

function stockGuard(int $product): array
{
    $row = DB::table('stock_product_balances')->where('product_id', $product)->first(['on_hand_qty', 'reserved_qty', 'unusable_qty']);

    return [$row->on_hand_qty, $row->reserved_qty, $row->unusable_qty];
}

it('reverses a wrong condition change equal and opposite on the same lot and condition, a second beyond the original REJECTED (CM-28; C-55; FU-03; criterion 10)', function () {
    $movement = StockWorld::conditionChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '3');
    expect(stockGuard($this->product))->toBe(['10.000', '0.000', '3.000']);

    expect(StockWorld::reverse($this->actor, $this->company->id, $movement))->toBe('COMMITTED');
    $reversal = DB::table('stock_movements')->where('reverses_movement_id', $movement)->sole();
    expect($reversal->movement_type)->toBe('MOVEMENT_REVERSAL')->and($reversal->reverses_movement_type)->toBe('CONDITION_CHANGE')->and($reversal->reason)->toBe('Pergerakan keliru')
        ->and(DB::table('stock_movement_entries')->where('stock_movement_id', $reversal->id)->orderBy('condition')->pluck('qty_delta_base', 'condition')->all())->toBe(['UNUSABLE' => '-3.000', 'USABLE' => '3.000'])
        ->and(stockGuard($this->product))->toBe(['10.000', '0.000', '0.000'])
        ->and(DB::table('stock_reversal_balances')->where('reversed_movement_id', $movement)->where('condition', 'UNUSABLE')->value('reversed_qty_base'))->toBe('3.000')
        ->and(reversalDifferences())->toBe([]);

    expect(StockWorld::reverse($this->actor, $this->company->id, $movement))->toContain('C-55')
        ->and(DB::table('stock_movements')->where('reverses_movement_id', $movement)->count())->toBe(1);
});

it('refuses a reversal whose units have moved on, with what is there (C-03)', function () {
    $movement = StockWorld::conditionChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '3');
    StockWorld::decrease($this->actor, $this->company, $this->product, $this->lot, $this->rack, '2', 'LOSS', 'UNUSABLE');

    expect(StockWorld::reverse($this->actor, $this->company->id, $movement))->toContain('C-03');
});

it('reverses a wrong adjustment decrease by restoring its consumption and its loss exactly, never by a MOVEMENT_REVERSAL (SF-LOSS; C-27; DATABASE §5.3; criterion 11)', function (string $type) {
    $remaining = fn (): string => (string) DB::table('lot_cost_balances')->where('lot_id', $this->lot)->value('remaining_cost');
    $before = $remaining();
    $movement = StockWorld::decrease($this->actor, $this->company, $this->product, $this->lot, $this->rack, '4', $type);
    expect(stockGuard($this->product))->toBe(['6.000', '0.000', '0.000'])->and($remaining())->not->toBe($before);
    $consumption = DB::table('stock_consumptions')->where('stock_movement_id', $movement)->value('id');
    $loss = fn (): string => (string) DB::table('cost_attributions')->where('stock_consumption_id', $consumption)->where('cost_class', 'LOSS')->selectRaw('coalesce(sum(amount), 0)::text as total')->value('total');
    expect($loss())->toBe('4000.00');

    expect(StockWorld::reverse($this->actor, $this->company->id, $movement))->toBe('COMMITTED');
    expect(DB::table('stock_movements')->where('reverses_movement_id', $movement)->count())->toBe(0)
        ->and(DB::table('stock_restorations')->where('stock_consumption_id', $consumption)->value('kind'))->toBe('LOSS_REVERSAL')
        ->and(DB::table('stock_consumption_balances')->where('stock_consumption_id', $consumption)->value('restored_qty_base'))->toBe('4.000')
        ->and($loss())->toBe('0.00')->and($remaining())->toBe($before)
        ->and(stockGuard($this->product))->toBe(['10.000', '0.000', '0.000'])
        ->and(reversalDifferences())->toBe([]);

    expect(StockWorld::reverse($this->actor, $this->company->id, $movement))->toContain('C-55');
})->with(['an adjustment decrease' => 'ADJUSTMENT_DECREASE', 'a loss' => 'LOSS', 'a count\'s loss' => 'COUNT_VARIANCE']);

it('reverses a pending case\'s recognition exactly, its counter capped (CM-28; DATABASE §5.8)', function () {
    $movement = StockWorld::recognition($this->actor, $this->company, $this->product, $this->lot, $this->rack, '2', '10');
    $case = DB::table('unattributed_loss_cases')->where('recognition_movement_id', $movement);
    $scope = fn () => DB::table('stock_scope_balances')->where('product_id', $this->product)->where('location_id', $this->rack)->where('condition', 'USABLE')->first(['claims_qty', 'pending_reduction_qty', 'qty']);
    expect(stockGuard($this->product))->toBe(['8.000', '0.000', '0.000'])->and($scope()->pending_reduction_qty)->toBe('2.000')->and((clone $case)->value('pending_qty_base'))->toBe('2.000');

    expect(StockWorld::reverse($this->actor, null, $movement))->toBe('COMMITTED');
    expect(stockGuard($this->product))->toBe(['10.000', '0.000', '0.000'])->and($scope()->pending_reduction_qty)->toBe('0.000')->and($scope()->qty)->toBe('10.000')
        ->and((clone $case)->value('reversed_qty_base'))->toBe('2.000')->and((clone $case)->value('pending_qty_base'))->toBe('0.000');

    expect(StockWorld::reverse($this->actor, null, $movement))->toContain('C-55');
});

it('reverses a surplus attribution, its flagged basis leaving with the units (AX-07; DATABASE §5.3)', function () {
    $movement = StockWorld::surplus($this->actor, $this->company, $this->product, $this->rack, '2', '5000');
    $lot = (int) DB::table('stock_movement_entries')->where('stock_movement_id', $movement)->value('lot_id');
    expect(stockGuard($this->product))->toBe(['12.000', '0.000', '0.000'])->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('5000.00');

    expect(StockWorld::reverse($this->actor, $this->company->id, $movement))->toBe('COMMITTED');
    expect(stockGuard($this->product))->toBe(['10.000', '0.000', '0.000'])->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('0.00')
        ->and(reversalDifferences())->toBe([]);
});

it('cannot commit a reversal leaving usable supply below the reservations without the actor\'s cut (IP-20; C-06; criterion 12)', function () {
    $project = $this->world->project(['WAREHOUSE'], '20');
    $demand = DB::table('project_lines')->where('project_id', $project->id)->first();
    $unit = (int) DB::table('product_units')->where('product_id', $demand->product_id)->where('is_base', true)->value('unit_id');
    $lot = StockWorld::lot($this->actor, $this->company, (int) $demand->product_id, $unit, $this->rack, '5');
    $movement = StockWorld::surplus($this->actor, $this->company, (int) $demand->product_id, $this->rack, '2', '5000');
    $reservation = ReservationWorld::reserve($this->actor, $demand->id, '7');
    expect($reservation)->toBe('COMMITTED')->and($lot)->toBeGreaterThan(0);
    $id = (int) DB::table('reservations')->where('project_line_id', $demand->id)->value('id');

    expect(StockWorld::reverse($this->actor, $this->company->id, $movement))->toContain('C-06')
        ->and(DB::table('stock_movements')->where('reverses_movement_id', $movement)->count())->toBe(0);

    expect(StockWorld::reverse($this->actor, $this->company->id, $movement, [$id => '2']))->toBe('COMMITTED')
        ->and(DB::table('reservations')->where('id', $id)->value('remaining_qty_base'))->toBe('5.000')
        ->and(reversalDifferences())->toBe([]);
});

it('leaves one of two racing reversals of one movement, never beyond its original (C-55; NX-11)', function () {
    $movement = StockWorld::conditionChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '3');
    [$actor, $company] = [$this->actor, $this->company->id];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => StockWorld::reverse($actor, $company, $movement), 'the first holds its locks'),
        'second' => static function () use ($actor, $company, $movement): string {
            Barrier::await('the first holds its locks');

            return StockWorld::reverse($actor, $company, $movement);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-55')
        ->and(DB::table('stock_movements')->where('reverses_movement_id', $movement)->count())->toBe(1)
        ->and(stockGuard($this->product))->toBe(['10.000', '0.000', '0.000'])->and(reversalDifferences())->toBe([]);
});
