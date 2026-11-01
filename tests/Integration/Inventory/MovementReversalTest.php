<?php

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

// TL-05 (TASK-040; NX-11; CM-28; C-55; C-27; C-06; FU-03; SF-LOSS; AZ-08; OD-15; SF-REVAL; DATABASE §5.3; §5.8): ROUTE-212 on fixture movements of each reversible type — a condition change with and without a serial, an adjustment decrease with its loss, a count's loss, a surplus attribution, a pending case's recognition, finding and conversion —, each re-run end to end by the Task that records it; two reversals of one movement racing; the reconciliation suite after each.

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
    return StockWorld::ledgerDifferences();
}

function scopeOf(int $product, int $rack, string $condition): object
{
    return DB::table('stock_scope_balances')->where('product_id', $product)->where('location_id', $rack)->where('condition', $condition)->first(['claims_qty', 'pending_reduction_qty', 'pending_addition_qty', 'qty']);
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
        ->and((clone $case)->value('reversed_qty_base'))->toBe('2.000')->and((clone $case)->value('pending_qty_base'))->toBe('0.000')
        ->and(reversalDifferences())->toBe([]);

    expect(StockWorld::reverse($this->actor, null, $movement))->toContain('C-55');
});

it('reverses a pending condition change exactly: its pair by one quantity, its counter raised once, the scope\'s two pending columns back (NX-05; CM-28; §5.8)', function () {
    $movement = StockWorld::pendingChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '2', '10');
    $case = DB::table('unattributed_loss_cases')->where('recognition_movement_id', $movement);
    expect(stockGuard($this->product))->toBe(['10.000', '0.000', '2.000'])
        ->and(scopeOf($this->product, $this->rack, 'USABLE')->pending_reduction_qty)->toBe('2.000')
        ->and(scopeOf($this->product, $this->rack, 'UNUSABLE')->pending_addition_qty)->toBe('2.000');

    expect(StockWorld::reverse($this->actor, null, $movement))->toBe('COMMITTED');
    expect(stockGuard($this->product))->toBe(['10.000', '0.000', '0.000'])
        ->and(scopeOf($this->product, $this->rack, 'USABLE')->pending_reduction_qty)->toBe('0.000')
        ->and(scopeOf($this->product, $this->rack, 'UNUSABLE')->pending_addition_qty)->toBe('0.000')
        ->and((clone $case)->value('reversed_qty_base'))->toBe('2.000')->and((clone $case)->value('pending_qty_base'))->toBe('0.000')
        ->and(reversalDifferences())->toBe([]);

    expect(StockWorld::reverse($this->actor, null, $movement))->toContain('C-55');
});

it('reverses a finding by its own quantity alone, never another finding\'s on the case (C-55; CM-28; §5.8)', function () {
    $recognition = StockWorld::recognition($this->actor, $this->company, $this->product, $this->lot, $this->rack, '6', '10');
    $case = (int) DB::table('unattributed_loss_cases')->where('recognition_movement_id', $recognition)->value('id');
    $first = StockWorld::found($this->actor, $this->company, $this->product, $this->rack, $case, '2');
    StockWorld::found($this->actor, $this->company, $this->product, $this->rack, $case, '3');
    expect(stockGuard($this->product))->toBe(['9.000', '0.000', '0.000'])->and(DB::table('unattributed_loss_cases')->where('id', $case)->value('pending_qty_base'))->toBe('1.000');

    expect(StockWorld::reverse($this->actor, null, $first))->toBe('COMMITTED');
    expect(stockGuard($this->product))->toBe(['7.000', '0.000', '0.000'])
        ->and(DB::table('unattributed_loss_cases')->where('id', $case)->value('found_qty_base'))->toBe('3.000')
        ->and(scopeOf($this->product, $this->rack, 'USABLE')->pending_reduction_qty)->toBe('3.000')
        ->and(reversalDifferences())->toBe([]);

    expect(StockWorld::reverse($this->actor, null, $first))->toContain('C-55')
        ->and(DB::table('unattributed_loss_cases')->where('id', $case)->value('found_qty_base'))->toBe('3.000');
});

it('reverses a conversion by inverting its rows, whole or in part, once (CM-28; §5.8; C-55)', function (string $converted, string $left, array $after, string $back) {
    $recognition = StockWorld::pendingChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '3', '10');
    $old = (int) DB::table('unattributed_loss_cases')->where('recognition_movement_id', $recognition)->value('id');
    [$movement, $successor] = StockWorld::conversion($this->actor, $this->company, $this->product, $this->lot, $this->rack, $old, $converted, '10');
    expect(stockGuard($this->product))->toBe($after)
        ->and(DB::table('unattributed_loss_cases')->where('id', $old)->value('pending_qty_base'))->toBe($left);

    expect(StockWorld::reverse($this->actor, null, $movement))->toBe('COMMITTED');
    $case = DB::table('unattributed_loss_cases')->where('id', $old)->first(['converted_qty_base', 'reversed_qty_base', 'pending_qty_base']);
    expect(stockGuard($this->product))->toBe(['10.000', '0.000', '3.000'])
        ->and($case->converted_qty_base)->toBe('0.000')->and($case->reversed_qty_base)->toBe('0.000')->and($case->pending_qty_base)->toBe('3.000')
        ->and(DB::table('unattributed_loss_cases')->where('id', $successor)->value('reversed_qty_base'))->toBe($back)
        ->and(DB::table('unattributed_loss_cases')->where('id', $successor)->value('pending_qty_base'))->toBe('0.000')
        ->and(scopeOf($this->product, $this->rack, 'USABLE')->pending_reduction_qty)->toBe('3.000')
        ->and(scopeOf($this->product, $this->rack, 'UNUSABLE')->pending_addition_qty)->toBe('3.000')
        ->and(reversalDifferences())->toBe([]);

    expect(StockWorld::reverse($this->actor, null, $movement))->toContain('C-55')
        ->and(stockGuard($this->product))->toBe(['10.000', '0.000', '3.000']);
})->with(['the whole' => ['3', '0.000', ['7.000', '0.000', '0.000'], '3.000'], 'a part' => ['1', '2.000', ['9.000', '0.000', '2.000'], '1.000']]);

it('brings a serial back with its condition change, the same unit and not another\'s balance (L-13; C-02; DATABASE §3)', function () {
    $serial = StockWorld::serial($this->product, $this->lot, $this->rack);
    $first = StockWorld::conditionChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '1', 'USABLE', $serial);
    expect(DB::table('serial_units')->where('id', $serial)->value('current_condition'))->toBe('UNUSABLE');

    expect(StockWorld::reverse($this->actor, $this->company->id, $first))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('id', $serial)->value('current_condition'))->toBe('USABLE')
        ->and(DB::table('stock_movement_entries')->join('stock_movements as m', 'm.id', '=', 'stock_movement_entries.stock_movement_id')->where('m.reverses_movement_id', $first)->whereNotNull('serial_unit_id')->count())->toBe(2);

    // The unit re-inspected back in the meantime while another stands in the unusable balance: refused, never a unit and its balance disagreeing.
    $again = StockWorld::conditionChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '1', 'USABLE', $serial);
    StockWorld::conditionChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '1', 'UNUSABLE', $serial);
    StockWorld::conditionChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '1');

    expect(StockWorld::reverse($this->actor, $this->company->id, $again))->toContain('C-02')
        ->and(DB::table('stock_movements')->where('reverses_movement_id', $again)->count())->toBe(0);
});

it('takes a surplus unit back out of the pool with its reversal, to the state before it (DATABASE §3; AX-07)', function () {
    $owner = Authorization::owner()->id;
    $movement = StockWorld::surplus($this->actor, $this->company, $this->product, $this->rack, '1', '5000', true);
    $serial = (int) DB::table('stock_movement_entries')->where('stock_movement_id', $movement)->value('serial_unit_id');
    expect(DB::table('serial_units')->where('id', $serial)->value('state'))->toBe('IN_STOCK');

    expect(StockWorld::reverse($owner, $this->company->id, $movement))->toBe('COMMITTED');
    $unit = DB::table('serial_units')->where('id', $serial)->first(['state', 'current_lot_id', 'current_location_id', 'current_condition']);
    expect($unit->state)->toBe('NEVER_RECEIVED')->and($unit->current_lot_id)->toBeNull()->and($unit->current_location_id)->toBeNull()->and($unit->current_condition)->toBeNull();
});

it('refuses and routes a loss reversal on a bearer outside the actor\'s grants, one audit event for that company, nothing else written (AZ-08; XL-1; SQ-23)', function () {
    $foreign = InvoiceWorld::make();
    $foreignActor = ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id;
    $lot = StockWorld::lot($foreignActor, $foreign->company, $this->product, $this->unit, $this->rack, '5');
    $movement = StockWorld::decrease($foreignActor, $foreign->company, $this->product, $lot, $this->rack, '2', 'COUNT_VARIANCE', 'USABLE', null, true);

    expect(StockWorld::reverse($this->actor, null, $movement))->toContain('AZ-08')
        ->and(DB::table('stock_restorations')->count())->toBe(0)
        ->and(DB::table('stock_consumption_balances')->where('stock_consumption_id', DB::table('stock_consumptions')->where('stock_movement_id', $movement)->value('id'))->value('restored_qty_base'))->toBe('0.000')
        ->and(DB::table('audit_events')->where('action', 'INV.MovementReversalRouted')->where('company_id', $foreign->company->id)->count())->toBe(1);

    expect(StockWorld::reverse($foreignActor, null, $movement))->toBe('COMMITTED')->and(reversalDifferences())->toBe([]);
});

it('returns a completed project its loss moved to ACTIVE, linked to the correction (SF-REVAL; BR-CR-05; ADMIN_FLOW §11)', function () {
    $project = $this->world->project(['WAREHOUSE'], '20');
    $movement = StockWorld::decrease($this->actor, $this->company, $this->product, $this->lot, $this->rack, '2', 'LOSS', 'USABLE', $project->id);
    DB::table('projects')->where('id', $project->id)->update(['state' => 'COMPLETED_NORMAL']);

    expect(StockWorld::reverse($this->actor, $this->company->id, $movement))->toBe('COMMITTED');
    $transition = DB::table('project_state_transitions')->where('project_id', $project->id)->where('transition_kind', 'REVALIDATE')->sole();
    expect(DB::table('projects')->where('id', $project->id)->value('state'))->toBe('ACTIVE')->and($transition->from_state)->toBe('COMPLETED_NORMAL')
        ->and($transition->correction_reference)->toBe(DB::table('stock_movements')->where('movement_type', 'RESTORATION')->latest('id')->value('public_id'));
});

it('leaves a surplus attribution to the Owner alone (OD-15; CM-27)', function () {
    $movement = StockWorld::surplus($this->actor, $this->company, $this->product, $this->rack, '2', '5000');

    expect(StockWorld::reverse($this->actor, $this->company->id, $movement))->toContain('OD-15')
        ->and(DB::table('stock_movements')->where('reverses_movement_id', $movement)->count())->toBe(0);
});

it('locks a condition change\'s reversal in the same statements whatever depends on its lot (QB-13; PFM-20)', function () {
    $count = function (int $movement): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        StockWorld::reverse($this->actor, $this->company->id, $movement);
        $statements = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $statements;
    };
    $quiet = $count(StockWorld::conditionChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '1'));
    $project = $this->world->project(['WAREHOUSE'], '20');
    StockWorld::decrease($this->actor, $this->company, $this->product, $this->lot, $this->rack, '1', 'LOSS', 'USABLE', $project->id);
    StockWorld::decrease($this->actor, $this->company, $this->product, $this->lot, $this->rack, '1', 'LOSS', 'USABLE', $project->id);

    expect($count(StockWorld::conditionChange($this->actor, $this->company, $this->product, $this->lot, $this->rack, '1')))->toBe($quiet);
});

it('reverses a surplus attribution, its flagged basis leaving with the units (AX-07; DATABASE §5.3)', function () {
    $movement = StockWorld::surplus($this->actor, $this->company, $this->product, $this->rack, '2', '5000');
    $lot = (int) DB::table('stock_movement_entries')->where('stock_movement_id', $movement)->value('lot_id');
    expect(stockGuard($this->product))->toBe(['12.000', '0.000', '0.000'])->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('5000.00');

    expect(StockWorld::reverse(Authorization::owner()->id, $this->company->id, $movement))->toBe('COMMITTED');
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

    $owner = Authorization::owner()->id;
    expect(StockWorld::reverse($owner, $this->company->id, $movement))->toContain('C-06')
        ->and(DB::table('stock_movements')->where('reverses_movement_id', $movement)->count())->toBe(0);

    expect(StockWorld::reverse($owner, $this->company->id, $movement, [$id => '2']))->toBe('COMMITTED')
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
