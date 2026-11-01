<?php

use App\Modules\CST\Queries\CostFigures;
use App\Modules\CST\Services\CostPosting;
use App\Modules\INV\Services\LossPosting;
use App\Modules\INV\Services\ReservationPredicate;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\PRJ\Data\Predicates;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\INV\StockMovementFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\CostWorld;
use Tests\Support\DatabaseTruncation;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\ReservationWorld;
use Tests\Support\StockWorld;

// TL-05 (TASK-042; WF-INV-04; WF-INV-06; SF-LOSS; AX-06; AX-30; NX-05; annex 9, 15, 16; C-03; C-06; C-07; C-24; C-27; C-28; C-55; L-15; L-36; L-38; L-42; L-44; L-49; M-04; M-30; GAP-028): condition changes, moves, adjustments, losses and their reversals on real sessions, the guards reconciled after each.

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
    $this->actor = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, ...AdjustmentWorld::CAPABILITIES])->id;
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->line = $this->demand['line'];
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
    $this->evidence = AdjustmentWorld::evidence();
});

function adjustmentGuard(int $product): array
{
    $row = DB::table('stock_product_balances as b')->where('product_id', $product)->first(['on_hand_qty', 'reserved_qty', 'unusable_qty']);

    return [$row->on_hand_qty, $row->reserved_qty, $row->unusable_qty];
}

function adjustmentClean(): void
{
    expect(StockWorld::ledgerDifferences())->toBe([])
        ->and(DB::table('stock_consumptions')->whereColumn('source_company_id', '<>', 'bearer_company_id')->where(fn ($where) => $where->whereNull('allocation_reason')->orWhereNull('allocation_authorized_by'))->count())->toBe(0)
        ->and(DB::table('cost_attributions as a')->join('stock_consumptions as c', 'c.id', '=', 'a.stock_consumption_id')->where(fn ($where) => $where->whereColumn('a.source_company_id', '<>', 'c.source_company_id')->orWhereColumn('a.bearer_company_id', '<>', 'c.bearer_company_id'))->count())->toBe(0);
}

function lossOn(?int $project, ?int $company = null): string
{
    return (string) DB::table('cost_attributions')->where('cost_class', 'LOSS')->when($project !== null, fn ($where) => $where->where('bearer_project_id', $project))->when($company !== null, fn ($where) => $where->where('bearer_company_id', $company)->whereNull('bearer_project_id'))->sum('amount');
}

it('moves 2 usable units to unusable on the same lot and rack with their reason and evidence, ON HAND unchanged and AVAILABLE down by 2 (WF-INV-04; C-07; BR-INV-01; criterion 1)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);

    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(adjustmentGuard($this->product))->toBe(['10.000', '0.000', '2.000']);
    $movement = DB::table('stock_movements')->where('movement_type', 'CONDITION_CHANGE')->sole();
    $audit = DB::table('audit_events')->where('action', 'INV.ConditionChanged')->sole();

    expect($movement->reason)->toBe('Unit rusak diperiksa')->and($movement->company_id)->toBeNull()
        ->and(DB::table('evidence_links')->where('stock_movement_id', $movement->id)->count())->toBe(1)
        ->and($audit->authority_class)->toBe('ADM_PLUS')->and($audit->reason)->toBe('Unit rusak diperiksa');
    adjustmentClean();
});

it('holds a movement\'s net-zero groups by the deferred trigger: a condition change or a move that does not net to zero cannot commit (C-07; L-36; criteria 1, 5)', function (string $type, array $second) {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    $other = ReceivingWorld::rack();
    $write = function () use ($type, $second, $lot, $other): void {
        DB::transaction(function () use ($type, $second, $lot, $other): void {
            $movement = StockMovementFactory::post($type, null);
            $base = ['stock_movement_id' => $movement, 'movement_type' => $type, 'product_id' => $this->product, 'quantity_scale' => 0, 'lot_id' => $lot, 'recorded_at' => now()];
            DB::table('stock_movement_entries')->insert([...$base, 'location_id' => $this->rack, 'condition' => 'USABLE', 'qty_delta_base' => '-5', 'command_id' => (string) Str::uuid(), 'command_line_no' => 1]);
            DB::table('stock_movement_entries')->insert([...$base, 'location_id' => $second[0] === 'other' ? $other : $this->rack, 'condition' => $second[1], 'qty_delta_base' => '6', 'command_id' => (string) Str::uuid(), 'command_line_no' => 2]);
        });
    };

    expect($write)->toThrow(PDOException::class, 'stock_movement_entries_c07_movement_balanced');
})->with([
    'a move of −5/+6' => ['LOCATION_MOVE', ['other', 'USABLE']],
    'an unbalanced condition change' => ['CONDITION_CHANGE', ['same', 'UNUSABLE']],
]);

it('commits a change or a loss of usable supply below the reservations only with the actor\'s cuts covering it, the project\'s reservation lowered with it (annex 9; BR-RSV-04; C-06; IP-20; criterion 2)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '8');
    expect(ReservationWorld::reserve($this->actor, $this->line, '8'))->toBe('COMMITTED');
    $reservation = (int) DB::table('reservations')->where('project_line_id', $this->line)->value('id');
    $pick = [AdjustmentWorld::pick($lot, $this->rack, '2')];

    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, $pick, evidence: [$this->evidence])))->toContain('C-06')
        ->and(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, $pick, [$reservation => '1'], [$this->evidence])))->toContain('C-06')
        ->and(adjustmentGuard($this->product))->toBe(['8.000', '8.000', '0.000']);
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, $pick, [$reservation => '2'], [$this->evidence])))->toBe('COMMITTED')
        ->and(adjustmentGuard($this->product))->toBe(['8.000', '6.000', '2.000'])
        ->and(DB::table('reservations')->where('id', $reservation)->value('remaining_qty_base'))->toBe('6.000')
        ->and(DB::table('reservation_events')->where('kind', 'CUT')->count())->toBe(1);

    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, $pick, evidence: [$this->evidence])))->toContain('C-06');
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], [$reservation => '1'], [$this->evidence])))->toBe('COMMITTED')
        ->and(adjustmentGuard($this->product))->toBe(['7.000', '5.000', '2.000']);
    adjustmentClean();
});

it('returns unusable units to usable only by the explicit re-inspection with its reason (WF-INV-04; criterion 3)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '3')], evidence: [$this->evidence])))->toBe('COMMITTED');

    expect(AdjustmentWorld::reinspect($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2', 'UNUSABLE')])))->toBe('COMMITTED')
        ->and(adjustmentGuard($this->product))->toBe(['10.000', '0.000', '1.000'])
        ->and(DB::table('audit_events')->where('action', 'INV.UnitsReinspected')->value('reason'))->toBe('Unit rusak diperiksa')
        // A re-inspection names unusable units, and a change usable ones.
        ->and(AdjustmentWorld::reinspect($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')])))->toContain('C-03')
        ->and(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1', 'UNUSABLE')], evidence: [$this->evidence])))->toContain('C-03');
    adjustmentClean();
});

it('records unidentified units only where every candidate lot is one company\'s, attributing nothing itself (L-42; DIR-026; criterion 4)', function () {
    $older = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '5');
    $newer = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '5');
    $entry = AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($newer, $this->rack, '1')], evidence: [$this->evidence], unidentified: true);

    // The actor's lot stands, though an older one is there: the oldest is a picking aid only.
    expect(AdjustmentWorld::change($this->actor, $entry))->toBe('COMMITTED')
        ->and(DB::table('stock_lot_balances as b')->where('lot_id', $newer)->where('condition', 'USABLE')->value('qty'))->toBe('4.000')
        ->and(DB::table('stock_lot_balances as b')->where('lot_id', $older)->where('condition', 'USABLE')->value('qty'))->toBe('5.000');

    $foreign = InvoiceWorld::make();
    StockWorld::lot(ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id, $foreign->company, $this->product, $this->unit, $this->rack, '5');
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($newer, $this->rack, '1')], evidence: [$this->evidence], unidentified: true, acknowledged: DB::table('stock_movements')->pluck('public_id')->all(), duplicateReason: 'Lagi')))->toContain('L-42')
        ->and(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($newer, $this->rack, '1')], evidence: [$this->evidence], unidentified: true)))->toContain('L-42');
    adjustmentClean();
});

it('moves exactly 5 of one lot and condition between racks, a first use born, no reservation or cost changed, the opposite move correcting it (L-36; FU-01; FU-02; J-MD-05; criterion 5)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    expect(ReservationWorld::reserve($this->actor, $this->line, '3'))->toBe('COMMITTED');
    $other = ReceivingWorld::rack();
    $held = fn (int $rack): ?string => DB::table('stock_lot_balances as b')->where('lot_id', $lot)->where('location_id', $rack)->where('condition', 'USABLE')->value('qty');
    $cost = DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost');

    expect(AdjustmentWorld::move($this->actor, $this->product, $lot, $this->rack, $other, '5'))->toBe('COMMITTED')
        ->and($held($this->rack))->toBe('5.000')->and($held($other))->toBe('5.000')
        ->and(adjustmentGuard($this->product))->toBe(['10.000', '3.000', '0.000'])
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe($cost)
        ->and(DB::table('audit_events')->where('action', 'INV.LocationMoved')->value('authority_class'))->toBe('ADM');

    expect(AdjustmentWorld::move($this->actor, $this->product, $lot, $this->rack, $this->rack, '1'))->toContain('C-07')
        ->and(AdjustmentWorld::move($this->actor, $this->product, $lot, $this->rack, $other, '6'))->toContain('C-03')
        ->and(AdjustmentWorld::move($this->actor, $this->product, $lot, $other, $this->rack, '5'))->toBe('COMMITTED')
        ->and($held($this->rack))->toBe('10.000')->and($held($other))->toBe('0.000');
    adjustmentClean();
});

it('moves a serial with its units, and refuses one not at the rack named (C-02; L-36)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '3');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $serial = StockWorld::serial($this->product, $lot, $this->rack);
    $other = ReceivingWorld::rack();

    expect(AdjustmentWorld::move($this->actor, $this->product, $lot, $this->rack, $other, '1'))->toContain('C-02')
        ->and(AdjustmentWorld::move($this->actor, $this->product, $lot, $other, $this->rack, '1', serials: [$serial]))->toContain('C-')
        ->and(AdjustmentWorld::move($this->actor, $this->product, $lot, $this->rack, $other, '1', serials: [$serial]))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('id', $serial)->value('current_location_id'))->toBe($other);
    adjustmentClean();
});

it('creates one row when two moves first use one rack together (M-30; FU-01; FU-02)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    $other = ReceivingWorld::rack();
    [$actor, $product, $rack] = [$this->actor, $this->product, $this->rack];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => AdjustmentWorld::move($actor, $product, $lot, $rack, $other, '2'), 'the first holds the rack'),
        'second' => static function () use ($actor, $product, $lot, $rack, $other): string {
            Barrier::await('the first holds the rack');

            return AdjustmentWorld::move($actor, $product, $lot, $rack, $other, '3');
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'COMMITTED'])
        ->and(DB::table('stock_lot_balances as b')->where('lot_id', $lot)->where('location_id', $other)->count())->toBe(1)
        ->and(DB::table('stock_scope_balances as s')->where('product_id', $product)->where('location_id', $other)->count())->toBe(1)
        ->and(DB::table('stock_lot_balances as b')->where('lot_id', $lot)->where('location_id', $other)->value('qty'))->toBe('5.000');
    adjustmentClean();
});

it('removes 2 shrunk units of 10,000 each and records one LOSS of 20,000 on the owning company in the same transaction, with its reason and audit event; no form offers an increase (WF-INV-06; L-15; SF-LOSS; C-24; criterion 6)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '10000');

    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(adjustmentGuard($this->product))->toBe(['8.000', '0.000', '0.000'])
        ->and(lossOn(null, $this->company->id))->toBe('20000.00');
    $consumption = DB::table('stock_consumptions')->where('kind', 'LOSS')->sole();
    $movement = DB::table('stock_movements')->where('id', $consumption->stock_movement_id)->sole();
    $audit = DB::table('audit_events')->where('action', 'INV.StockAdjusted')->sole();

    expect($movement->movement_type)->toBe('ADJUSTMENT_DECREASE')->and($movement->reason)->toBe('Unit rusak diperiksa')
        ->and($consumption->bearer_company_id)->toBe($this->company->id)->and($consumption->bearer_project_id)->toBeNull()
        ->and($audit->authority_class)->toBe('ADM_PLUS')->and($audit->reason)->toBe('Unit rusak diperiksa')
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('80000.00');

    // Any other physical loss is a LOSS movement on the same path.
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence]), 'OTHER'))->toBe('COMMITTED')
        ->and(DB::table('stock_movements')->where('movement_type', 'LOSS')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'INV.LossRecognized')->count())->toBe(1);
    adjustmentClean();
});

it('records a client-damaged unit\'s disposal as one LOSS of 50,000 on the project its case names, the case\'s single outcome (annex 16; L-38; CM-11; criterion 7)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '50000');
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $project = $this->demand['project']->id;
    $case = CorrectionCase::factory()->create(['company_id' => $this->company->id, 'case_kind' => 'DAMAGED_UNIT', 'causal_project_id' => $project, 'source_lot_id' => $lot])->id;

    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL', $case))->toBe('COMMITTED')
        ->and(lossOn($project))->toBe('50000.00');
    $consumption = DB::table('stock_consumptions')->where('kind', 'LOSS')->sole();

    expect((int) $consumption->correction_case_id)->toBe($case)->and((int) $consumption->bearer_project_id)->toBe($project);
    adjustmentClean();
});

it('answers a damaged unit to the case open on its lot though the form names none, its single outcome spent once, a usable unit never its; several open asks the actor (L-38; CM-11; CS-04; C-24; criterion 7)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '5', '50000');
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '3')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $project = $this->demand['project']->id;
    $case = CorrectionCase::factory()->create(['company_id' => $this->company->id, 'case_kind' => 'DAMAGED_UNIT', 'causal_project_id' => $project, 'source_lot_id' => $lot])->id;

    // A usable unit is no case's: its owner bears it.
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(DB::table('stock_consumptions')->where('bearer_project_id', $project)->count())->toBe(0);
    // Naming a case for it is refused.
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence]), 'SHRINKAGE', $case))->toContain('C-14');

    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL'))->toBe('COMMITTED')
        ->and(lossOn($project))->toBe('50000.00')
        ->and((int) DB::table('stock_consumptions')->where('bearer_project_id', $project)->value('correction_case_id'))->toBe($case);

    // Its outcome spent: a further damaged unit is its owner's, and naming the case again is refused.
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL', $case))->toContain('C-24')
        ->and(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL'))->toBe('COMMITTED')
        ->and(lossOn($project))->toBe('50000.00');
    adjustmentClean();

    $second = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2');
    foreach ([1, 2] as $number) {
        CorrectionCase::factory()->create(['company_id' => $this->company->id, 'case_kind' => 'DAMAGED_UNIT', 'causal_project_id' => $project, 'source_lot_id' => $second]);
    }
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($second, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($second, $this->rack, '1', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL'))->toContain('C-14');
});

it('spends a receipt\'s damage once across the racks picked, and a repaired unit is no longer the project\'s (L-38; criterion 8)', function () {
    $project = $this->demand['project']->id;
    $lot = AdjustmentWorld::damagedReceipt($this->actor, $this->company, $this->product, $this->unit, $this->rack, '4', '2', '1000', $project, $this->line);
    $other = ReceivingWorld::rack();
    expect(AdjustmentWorld::move($this->actor, $this->product, $lot, $this->rack, $other, '2', 'UNUSABLE'))->toBe('COMMITTED');
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2')], evidence: [$this->evidence])))->toBe('COMMITTED');

    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $other, '2', 'UNUSABLE'), AdjustmentWorld::pick($lot, $this->rack, '2', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL'))->toBe('COMMITTED')
        ->and(lossOn($project))->toBe('2000.00')->and(lossOn(null, $this->company->id))->toBe('2000.00');
    adjustmentClean();

    $repaired = AdjustmentWorld::damagedReceipt($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2', '1', '1000', $project, $this->line);
    expect(AdjustmentWorld::reinspect($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($repaired, $this->rack, '1', 'UNUSABLE')])))->toBe('COMMITTED')
        ->and(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($repaired, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($repaired, $this->rack, '1', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL'))->toBe('COMMITTED')
        ->and(lossOn($project))->toBe('2000.00');
    adjustmentClean();
});

it('counts a re-inspection undone by its reversal as the project\'s damage again, for the loss and for completion (L-38; C-55; criterion 8)', function () {
    $project = $this->demand['project']->id;
    $lot = AdjustmentWorld::damagedReceipt($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2', '2', '1000', $project, $this->line);
    $items = fn (): array => array_map(fn ($item): string => $item->key, app(ReservationPredicate::class)->states([$project], false)[$project][Predicates::STOCK]->items);

    expect(AdjustmentWorld::reinspect($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2', 'UNUSABLE')])))->toBe('COMMITTED')
        ->and($items())->not->toContain('projects.item.unusable_units')
        ->and(StockWorld::reverse($this->actor, null, (int) DB::table('stock_movements')->where('movement_type', 'CONDITION_CHANGE')->value('id')))->toBe('COMMITTED')
        ->and($items())->toContain('projects.item.unusable_units')
        ->and(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL'))->toBe('COMMITTED')
        ->and(lossOn($project))->toBe('2000.00')
        ->and($items())->not->toContain('projects.item.unusable_units');
    adjustmentClean();
});

it('changes a serial\'s condition with its unit, one change from two commands leaving one, and refuses disposing of usable units (C-02; L-13; WF-INV-06)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $serial = StockWorld::serial($this->product, $lot, $this->rack);
    $pick = AdjustmentWorld::pick($lot, $this->rack, '1', 'USABLE', $serial);

    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [$pick], evidence: [$this->evidence]), 'DISPOSAL'))->toContain('C-03');
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toContain('C-02')
        ->and(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [$pick], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('id', $serial)->value('current_condition'))->toBe('UNUSABLE')
        ->and(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [$pick], evidence: [$this->evidence])))->toContain('C-02')
        ->and(AdjustmentWorld::reinspect($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1', 'UNUSABLE', $serial)])))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('id', $serial)->value('current_condition'))->toBe('USABLE');
});

it('charges units damaged at the receipt of a project-linked line to that project, as far as that damage goes, the rest to the owning company (L-38; criterion 8)', function () {
    $project = $this->demand['project']->id;
    $lot = AdjustmentWorld::damagedReceipt($this->actor, $this->company, $this->product, $this->unit, $this->rack, '3', '2', '1000', $project, $this->line);
    expect(adjustmentGuard($this->product))->toBe(['5.000', '0.000', '2.000']);
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED');

    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '3', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL'))->toBe('COMMITTED')
        ->and(lossOn($project))->toBe('2000.00')
        ->and(lossOn(null, $this->company->id))->toBe('1000.00')
        ->and(DB::table('stock_consumptions')->where('kind', 'LOSS')->count())->toBe(2);
    adjustmentClean();
});

it('carries one allocation on a loss charged to another company\'s project, with its reason and authority; otherwise refused and routed with an event per company (L-44; XL-1; AZ-08; SQ-23; UXS-22; criterion 9)', function () {
    $foreign = InvoiceWorld::make();
    $owner = ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id;
    $other = DispatchWorld::demand($foreign);
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '5', '1000');
    $case = CorrectionCase::factory()->create(['company_id' => $foreign->company->id, 'case_kind' => 'DAMAGED_UNIT', 'causal_project_id' => $other['project']->id, 'source_lot_id' => $lot])->id;
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $pick = [AdjustmentWorld::pick($lot, $this->rack, '1', 'UNUSABLE')];
    $events = fn (int $company): int => DB::table('audit_events')->where('action', 'INV.LossRouted')->where('company_id', $company)->count();
    $bearerOnly = ReceivingWorld::actor($foreign->company, AdjustmentWorld::CAPABILITIES)->id;
    $allowed = ReceivingWorld::actor($foreign->company, [...AdjustmentWorld::CAPABILITIES, 'stock.allocate_intercompany'])->id;

    // Without the bearer in scope, without the authority, without the reason: each refused, routed to both, nothing else.
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, $pick, evidence: [$this->evidence]), 'OTHER', $case, 'Alasan'))->toContain('AZ-08')
        ->and($events($this->company->id))->toBe(1)->and($events($foreign->company->id))->toBe(1)
        ->and(AdjustmentWorld::adjust($bearerOnly, AdjustmentWorld::entry($this->product, $pick, evidence: [$this->evidence]), 'OTHER', $case, 'Alasan'))->toContain('AZ-08')
        ->and(AdjustmentWorld::adjust($allowed, AdjustmentWorld::entry($this->product, $pick, evidence: [$this->evidence]), 'OTHER', $case))->toContain('AZ-08')
        ->and($events($foreign->company->id))->toBe(3)
        ->and(DB::table('stock_consumptions')->where('kind', 'LOSS')->count())->toBe(0);

    // No grant on the lot's company is needed: the consuming side's authority carries it (XL-1).
    expect(AdjustmentWorld::adjust($allowed, AdjustmentWorld::entry($this->product, $pick, evidence: [$this->evidence]), 'OTHER', $case, 'Unit rusak di proyek B'))->toBe('COMMITTED');
    $consumption = DB::table('stock_consumptions')->where('kind', 'LOSS')->sole();

    expect($consumption->source_company_id)->toBe($this->company->id)->and($consumption->bearer_company_id)->toBe($foreign->company->id)
        ->and($consumption->allocation_reason)->toBe('Unit rusak di proyek B')->and($consumption->allocation_authorized_by)->toBe($allowed)
        ->and(lossOn($other['project']->id))->toBe('1000.00')
        ->and(json_decode(DB::table('audit_events')->where('action', 'INV.LossRecognized')->value('after'), true)['allocated'])->toBeTrue();
    adjustmentClean();

    // The loss by its family: the bearer sees it masked of the source, the source the draw masked of the bearer (PJ-05; PJ-06; criterion 16).
    $figures = app(CostFigures::class);
    $bearer = Authorization::admin([$foreign->company->id], ['cost.view'])->id;
    $source = Authorization::admin([$this->company->id], ['cost.view'])->id;
    $movement = (int) $consumption->stock_movement_id;
    expect($figures->lossesOf($bearer, [$movement]))->toBe([$movement => '1000.00'])
        ->and($figures->lossesOf($source, [$movement]))->toBe([])
        ->and($figures->lossesOf($this->actor, [$movement]))->toBe([])
        ->and($owner)->toBeInt();
});

it('reclassifies two units dispatched to X at 50,000 with one lost in transit: HPP −50,000 and LOSS +50,000, no stock movement, the cost staying 100,000, and reverses it exactly (annex 15; CM-07; SF-LOSS; criterion 10)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2', '50000');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '2', [DispatchWorld::pick($lot, $this->rack, '2')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    $world = CostWorld::resume(['company' => $this->company->id, 'product' => $this->product, 'location' => $this->rack, 'actor' => $this->actor]);
    $project = $this->demand['project']->id;
    $class = fn (string $class): string => (string) DB::table('cost_attributions')->where('bearer_project_id', $project)->where('cost_class', $class)->sum('amount');
    $movements = DB::table('stock_movements')->count();
    $locks = CostPosting::consumptionLocks($row->consumption)->add('stock_consumption_balances', ['stock_consumption_id' => $row->consumption]);

    $world->expect($world->run($locks, fn (CommandContext $context) => app(LossPosting::class)->reclassify($context, $row->consumption, '1', BusinessDate::today())));
    expect($class('HPP'))->toBe('50000.00')->and($class('LOSS'))->toBe('50000.00')
        ->and(DB::table('stock_movements')->count())->toBe($movements)
        ->and(DB::table('stock_consumption_balances as b')->where('stock_consumption_id', $row->consumption)->value('reclassified_qty_base'))->toBe('1.000');

    // Above the open quantity refused; found again, the exact inverse.
    expect($world->run($locks, fn (CommandContext $context) => app(LossPosting::class)->reclassify($context, $row->consumption, '2', BusinessDate::today()))->code)->toBe('C-27');
    $world->expect($world->run($locks, fn (CommandContext $context) => app(LossPosting::class)->reverseReclassification($context, $row->consumption, '1', BusinessDate::today())));
    expect($class('HPP'))->toBe('100000.00')->and($class('LOSS'))->toBe('0.00')
        ->and(DB::table('stock_consumption_balances as b')->where('stock_consumption_id', $row->consumption)->value('reclassified_qty_base'))->toBe('0.000');
    adjustmentClean();
});

it('reverses a loss of several racks and serials to each one\'s place, and an allocated loss with its allocation (CM-34; C-27; L-44; criterion 11)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '1000');
    $other = ReceivingWorld::rack();
    expect(AdjustmentWorld::move($this->actor, $this->product, $lot, $this->rack, $other, '4'))->toBe('COMMITTED');
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2'), AdjustmentWorld::pick($lot, $other, '1')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $movement = (int) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->value('id');
    expect(lossOn(null, $this->company->id))->toBe('3000.00')->and(DB::table('stock_consumptions')->where('stock_movement_id', $movement)->count())->toBe(2);

    expect(AdjustmentWorld::reverse($this->actor, $this->company->id, $movement))->toBe('COMMITTED')
        ->and(lossOn(null, $this->company->id))->toBe('0.00')
        ->and(DB::table('stock_lot_balances as b')->where('lot_id', $lot)->where('location_id', $this->rack)->where('condition', 'USABLE')->value('qty'))->toBe('6.000')
        ->and(DB::table('stock_lot_balances as b')->where('lot_id', $lot)->where('location_id', $other)->where('condition', 'USABLE')->value('qty'))->toBe('4.000');
    adjustmentClean();

    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    [$first, $second] = [StockWorld::serial($this->product, $lot, $this->rack), StockWorld::serial($this->product, $lot, $this->rack)];
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1', 'USABLE', $first), AdjustmentWorld::pick($lot, $this->rack, '1', 'USABLE', $second)], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->whereIn('id', [$first, $second])->where('state', 'LOST')->count())->toBe(2);
    expect(AdjustmentWorld::reverse($this->actor, $this->company->id, (int) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->orderByDesc('id')->value('id')))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->whereIn('id', [$first, $second])->where('state', 'IN_STOCK')->count())->toBe(2);

    // An allocated loss comes back with its allocation: the bearer's cost at zero, the source's lot whole.
    $foreign = InvoiceWorld::make();
    $project = DispatchWorld::demand($foreign)['project']->id;
    $allowed = ReceivingWorld::actor($foreign->company, [...AdjustmentWorld::CAPABILITIES, 'stock.allocate_intercompany'])->id;
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => false]);
    $case = CorrectionCase::factory()->create(['company_id' => $foreign->company->id, 'case_kind' => 'DAMAGED_UNIT', 'causal_project_id' => $project, 'source_lot_id' => $lot])->id;
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(AdjustmentWorld::adjust($allowed, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL', $case, 'Unit rusak proyek B'))->toBe('COMMITTED')
        ->and(lossOn($project))->toBe('1000.00');
    $held = DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost');
    expect(AdjustmentWorld::reverse($this->actor, null, (int) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->orderByDesc('id')->value('id')))->toContain('AZ-08')
        ->and(AdjustmentWorld::reverse($allowed, null, (int) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->orderByDesc('id')->value('id')))->toBe('COMMITTED')
        ->and(lossOn($project))->toBe('0.00')
        ->and(BigDecimal::of((string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->minus((string) $held)->toFloat())->toEqual(1000.0);
    adjustmentClean();
});

it('reverses a loss exactly: the loss contra\'d, its allocation with it, its units back only through capped restorations, a second reversal refused (CM-34; C-27; C-55; criterion 11)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '10000');
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $movement = (int) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->value('id');

    expect(AdjustmentWorld::reverse($this->actor, $this->company->id, $movement))->toBe('COMMITTED')
        ->and(lossOn(null, $this->company->id))->toBe('0.00')
        ->and(adjustmentGuard($this->product))->toBe(['10.000', '0.000', '0.000'])
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('100000.00')
        ->and(DB::table('stock_restorations')->where('kind', 'LOSS_REVERSAL')->sum('qty_base'))->toEqual(2)
        ->and(AdjustmentWorld::reverse($this->actor, $this->company->id, $movement))->toContain('C-55')
        // Only a loss's record: a condition change is undone by its linked reversal.
        ->and(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(AdjustmentWorld::reverse($this->actor, null, (int) DB::table('stock_movements')->where('movement_type', 'CONDITION_CHANGE')->value('id')))->toContain('C-55');
    adjustmentClean();
});

it('loses a unit once: a second loss of one serial, or of what the lot no longer holds, refused; the serial back only by the reversal (C-24; C-03; BR-INV-13; criterion 12)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '3');
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '4')], evidence: [$this->evidence])))->toContain('C-03');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $serial = StockWorld::serial($this->product, $lot, $this->rack);
    $pick = [AdjustmentWorld::pick($lot, $this->rack, '1', 'USABLE', $serial)];

    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, $pick, evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('id', $serial)->value('state'))->toBe('LOST')
        ->and(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, $pick, evidence: [$this->evidence], acknowledged: DB::table('stock_movements')->pluck('public_id')->all(), duplicateReason: 'Lagi')))->toContain('C-24');

    expect(AdjustmentWorld::reverse($this->actor, $this->company->id, (int) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->value('id')))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('id', $serial)->value('state'))->toBe('IN_STOCK');
    adjustmentClean();
});

it('recognizes a supplier return\'s unrecovered share once on its causal project as a cost-only loss, with an allocation when the project\'s company differs (CM-15; CM-16; L-44; criterion 13)', function () {
    $a = CostWorld::make();
    $b = CostWorld::make();
    $lot = $a->receive($a->line($a->purchase(), '4', '40000.00'), '4');
    $project = $b->project($b->company, 'ACTIVE');
    $case = CorrectionCase::factory()->create(['company_id' => $a->company->id, 'case_kind' => 'PURCHASE_RETURN', 'causal_project_id' => $project->id, 'source_lot_id' => $lot])->id;
    $consumption = $a->purchaseReturn($lot, '1', $case);
    $locks = CostPosting::consumptionLocks($consumption)->add('projects', ['id' => $project->id]);
    $share = fn (CommandContext $context, ?string $reason) => app(LossPosting::class)->unrecovered($context, $consumption, Money::of('3000.00'), $reason, BusinessDate::today());

    expect($a->run($locks, fn (CommandContext $context) => $share($context, 'Ongkos kembali'))->code)->toBe('AZ-08');

    $allowed = Authorization::admin([$a->company->id, $b->company->id], [...DispatchWorld::CAPABILITIES, 'stock.allocate_intercompany'])->id;
    $both = CostWorld::resume(['company' => $a->company->id, 'product' => $a->productId, 'location' => $a->locationId, 'actor' => $allowed]);
    $both->expect($both->run($locks, fn (CommandContext $context) => $share($context, 'Ongkos kembali')));
    $loss = DB::table('cost_attributions')->where('stock_consumption_id', $consumption)->where('cost_class', 'LOSS')->sole();

    expect($loss->amount)->toBe('3000.00')->and($loss->bearer_project_id)->toBe($project->id)->and($loss->allocation_reason)->toBe('Ongkos kembali')
        ->and(DB::table('stock_movements')->where('movement_type', 'LOSS')->count())->toBe(0);

    // Never more than the return still carries: 10,000 returned, 3,000 settled.
    expect($both->run($locks, fn (CommandContext $context) => app(LossPosting::class)->unrecovered($context, $consumption, Money::of('7000.01'), 'Lagi', BusinessDate::today()))->code)->toBe('C-24');
});

it('refuses a likely duplicate of a change or an adjustment with its match, and commits it only with the actor\'s reason naming it (L-49; BD-13; UXS-15; criterion 14)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    $change = fn (array $acknowledged = [], ?string $reason = null) => AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence], acknowledged: $acknowledged, duplicateReason: $reason));
    expect($change())->toBe('COMMITTED');
    $first = DB::table('stock_movements')->where('movement_type', 'CONDITION_CHANGE')->value('public_id');

    expect($change())->toContain('L-49')
        ->and($change([$first], 'Dua unit rusak terpisah'))->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'INV.ConditionChangedDuplicateConfirmed')->count())->toBe(1)
        ->and(json_decode(DB::table('audit_events')->where('action', 'INV.ConditionChangedDuplicateConfirmed')->value('after'), true)['duplicate_reason'])->toBe('Dua unit rusak terpisah');

    $adjust = fn (array $acknowledged = [], ?string $reason = null) => AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence], acknowledged: $acknowledged, duplicateReason: $reason));
    expect($adjust())->toBe('COMMITTED');
    $loss = DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->value('public_id');
    expect($adjust())->toContain('L-49')
        ->and($adjust([$loss], 'Dua susut terpisah'))->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'INV.StockAdjustedDuplicateConfirmed')->count())->toBe(1);
    adjustmentClean();

    // A quantity split between two picks is the same quantity; a loss given back no longer matches.
    $other = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($other, $this->rack, '2'), AdjustmentWorld::pick($other, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $split = (int) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->orderByDesc('id')->value('id');
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($other, $this->rack, '3')], evidence: [$this->evidence])))->toContain('L-49')
        ->and(AdjustmentWorld::reverse($this->actor, null, $split))->toBe('COMMITTED')
        ->and(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($other, $this->rack, '3')], evidence: [$this->evidence])))->toBe('COMMITTED');
});

it('moves a counted scope\'s version, so an open count of it is stale when applied and nothing is overwritten (M-04; L-14; C-33; criterion 15)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    $version = fn (string $condition): int => (int) DB::table('stock_scope_balances as s')->where('product_id', $this->product)->where('location_id', $this->rack)->where('condition', $condition)->value('version');
    $before = $version('USABLE');

    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and($version('USABLE'))->toBe($before + 1)
        ->and(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and($version('USABLE'))->toBe($before + 2)
        ->and(AdjustmentWorld::move($this->actor, $this->product, $lot, $this->rack, ReceivingWorld::rack(), '1'))->toBe('COMMITTED')
        ->and($version('USABLE'))->toBe($before + 3);
});

it('leaves one of a loss and a dispatch racing for one lot\'s last units, each unit\'s cost taken once (LK-15; LK-23; C-03; C-04)', function (bool $lossFirst) {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '1000');
    [$actor, $company, $line, $unit, $rack, $product, $evidence] = [$this->actor, $this->company->id, $this->line, $this->unit, $this->rack, $this->product, $this->evidence];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $lossFirst
            ? AdjustmentWorld::adjust($actor, AdjustmentWorld::entry($product, [AdjustmentWorld::pick($lot, $rack, '1')], evidence: [$evidence]))
            : DispatchWorld::dispatch($actor, $company, $line, $unit, '1', [DispatchWorld::pick($lot, $rack, '1')]), 'the first holds the lot'),
        'second' => static function () use ($lossFirst, $actor, $company, $line, $unit, $lot, $rack, $product, $evidence): string {
            Barrier::await('the first holds the lot');

            return $lossFirst
                ? DispatchWorld::dispatch($actor, $company, $line, $unit, '1', [DispatchWorld::pick($lot, $rack, '1')])
                : AdjustmentWorld::adjust($actor, AdjustmentWorld::entry($product, [AdjustmentWorld::pick($lot, $rack, '1')], evidence: [$evidence]));
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-0')
        ->and(adjustmentGuard($product))->toBe(['0.000', '0.000', '0.000'])
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->first(['remaining_qty_base', 'remaining_cost']))->toMatchObject(['remaining_qty_base' => '0.000', 'remaining_cost' => '0.00']);
    adjustmentClean();
})->with(['the loss first' => true, 'the dispatch first' => false]);

it('undoes these commands\' movements through ROUTE-212 end to end: a change by its linked reversal, a disposal by its restorations (CM-28; C-55)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $change = (int) DB::table('stock_movements')->where('movement_type', 'CONDITION_CHANGE')->value('id');

    expect(StockWorld::reverse($this->actor, null, $change))->toBe('COMMITTED')->and(adjustmentGuard($this->product))->toBe(['10.000', '0.000', '0.000'])
        ->and(StockWorld::reverse($this->actor, null, $change))->toContain('C-55');
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2')], evidence: [$this->evidence])))->toBe('COMMITTED');
    expect(StockWorld::reverse($this->actor, $this->company->id, (int) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->value('id')))->toBe('COMMITTED')
        ->and(adjustmentGuard($this->product))->toBe(['10.000', '0.000', '0.000']);
    adjustmentClean();
});

it('blocks completion while a project\'s causal units stay unusable, and not once they are disposed (WF-PRJ-03 predicate 8; L-38; L-11)', function () {
    $project = $this->demand['project']->id;
    $lot = AdjustmentWorld::damagedReceipt($this->actor, $this->company, $this->product, $this->unit, $this->rack, '3', '2', '1000', $project, $this->line);
    $items = fn (): array => array_map(fn ($item): string => $item->key, app(ReservationPredicate::class)->states([$project], false)[$project][Predicates::STOCK]->items);

    expect($items())->toContain('projects.item.unusable_units');
    // A loss on a completed project is a residual command: it does not reopen it.
    DB::table('projects')->where('id', $project)->update(['state' => 'COMPLETED_NORMAL']);
    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '2', 'UNUSABLE')], evidence: [$this->evidence]), 'DISPOSAL'))->toBe('COMMITTED')
        ->and(DB::table('projects')->where('id', $project)->value('state'))->toBe('COMPLETED_NORMAL')
        ->and($items())->not->toContain('projects.item.unusable_units');
    adjustmentClean();
});
