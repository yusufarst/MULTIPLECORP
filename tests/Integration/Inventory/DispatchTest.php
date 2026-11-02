<?php

use App\Modules\CST\Queries\CostFigures;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\INV\Queries\DispatchQueries;
use App\Modules\INV\Services\DispatchSubjects;
use App\Support\Money;
use Database\Factories\CAT\ProductUnitFactory;
use Database\Factories\CAT\UnitFactory;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\DeliveryWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\ReservationWorld;
use Tests\Support\StockWorld;

// TL-05 (TASK-041; WF-INV-03; WF-INV-07; AX-03; AX-10; AX-35; annex 7, 10, 12; M-01; M-03; M-05; M-06; C-02; C-04; C-27; C-28; L-07; L-31; L-49; XL-1; PX-08): the dispatch, its allocation, its restoration and its substitution on real sessions, the guards reconciled after each.

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
    $this->actor = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES)->id;
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->line = $this->demand['line'];
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
});

function dispatchGuard(int $product): array
{
    $row = DB::table('stock_product_balances as b')->where('product_id', $product)->first(['on_hand_qty', 'reserved_qty', 'unusable_qty']);

    return [$row->on_hand_qty, $row->reserved_qty, $row->unusable_qty];
}

function dispatchLineGuard(int $line): array
{
    $row = DB::table('project_line_balances as b')->where('project_line_id', $line)->first(['reserved_qty_base', 'dispatched_net_qty_base']);

    return [$row->reserved_qty_base, $row->dispatched_net_qty_base];
}

function dispatchClean(): void
{
    expect(StockWorld::ledgerDifferences())->toBe([])
        // C-28's DQ: a consumption across companies carries its reason and authorizing actor.
        ->and(DB::table('stock_consumptions')->whereColumn('source_company_id', '<>', 'bearer_company_id')->where(fn ($where) => $where->whereNull('allocation_reason')->orWhereNull('allocation_authorized_by'))->count())->toBe(0)
        // The source is the lot's, the cost row's sides its consumption's: no row contradicts its cause.
        ->and(DB::table('stock_consumptions as c')->join('inventory_lots as i', 'i.id', '=', 'c.lot_id')->whereColumn('c.source_company_id', '<>', 'i.source_company_id')->count())->toBe(0)
        ->and(DB::table('cost_attributions as a')->join('stock_consumptions as c', 'c.id', '=', 'a.stock_consumption_id')->where(fn ($where) => $where->whereColumn('a.source_company_id', '<>', 'c.source_company_id')->orWhereColumn('a.bearer_company_id', '<>', 'c.bearer_company_id'))->count())->toBe(0);
}

/** Σ attributed + what the lot still carries (M-07). */
function dispatchLotCostTotal(int $lot): string
{
    $held = (string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost');
    $drawn = (string) DB::table('cost_attributions')->whereIn('stock_consumption_id', DB::table('stock_consumptions')->where('lot_id', $lot)->pluck('id'))->sum('amount');

    return Money::of($held)->plus(Money::of($drawn))->toString();
}

it('dispatches the reserved units as one physical effect: 10, 5, 2 becomes 5, 0, 2 with 3 available, the reservation CONSUMED (annex 7; BR-INV-02; AC-22; criterion 1)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    StockWorld::conditionChange($this->actor, $this->company, $this->product, $lot, $this->rack, '2');
    expect(ReservationWorld::reserve($this->actor, $this->line, '5'))->toBe('COMMITTED');

    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '5', [DispatchWorld::pick($lot, $this->rack, '5')]))->toBe('COMMITTED');

    expect(dispatchGuard($this->product))->toBe(['5.000', '0.000', '2.000'])
        ->and(dispatchLineGuard($this->line))->toBe(['0.000', '5.000'])
        ->and(DB::table('reservations')->where('project_line_id', $this->line)->value('state'))->toBe('CONSUMED')
        ->and(DB::table('reservation_events')->where('kind', 'CONSUME')->value('qty_delta_base'))->toBe('-5.000');
    $dispatch = DB::table('dispatch_lines')->where('project_line_id', $this->line)->sole();
    expect($dispatch->reserved_qty_base)->toBe('5.000')->and($dispatch->unreserved_qty_base)->toBe('0.000')->and($dispatch->reservation_id)->not->toBeNull();
    dispatchClean();
});

it('takes a line with no reservation from AVAILABLE in the same action, the rest of a partial reservation too (WF-INV-03; BR-XD-02; AC-20; criterion 2)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '3', [DispatchWorld::pick($lot, $this->rack, '3')]))->toBe('COMMITTED');
    $first = DB::table('dispatch_lines')->where('project_line_id', $this->line)->sole();
    expect($first->unreserved_qty_base)->toBe('3.000')->and($first->reservation_id)->toBeNull();

    expect(ReservationWorld::reserve($this->actor, $this->line, '2'))->toBe('COMMITTED');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '4', [DispatchWorld::pick($lot, $this->rack, '4')]))->toBe('COMMITTED');
    $second = DB::table('dispatch_lines')->where('project_line_id', $this->line)->orderByDesc('id')->first();
    expect($second->reserved_qty_base)->toBe('2.000')->and($second->unreserved_qty_base)->toBe('2.000');
    expect(dispatchGuard($this->product))->toBe(['3.000', '0.000', '0.000'])->and(dispatchLineGuard($this->line))->toBe(['0.000', '7.000']);
    dispatchClean();
});

it('refuses above the line\'s remainder, above the supply, and picks that do not add up, committing nothing (PX-03; C-04; C-03; L-04)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '25');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '21', [DispatchWorld::pick($lot, $this->rack, '21')]))->toContain('PX-03');

    // Another project's reservation holds the rest of the pool: its supply is not this line's (C-04; M-03).
    $other = DispatchWorld::demand($this->world);
    expect(ReservationWorld::reserve($this->actor, $other['line'], '20'))->toBe('COMMITTED');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '6', [DispatchWorld::pick($lot, $this->rack, '6')]))->toContain('C-04');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '5', [DispatchWorld::pick($lot, $this->rack, '4')]))->toContain('C-03');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '5', [DispatchWorld::pick($lot, ReceivingWorld::rack(), '5')]))->toContain('C-03');
    expect(DB::table('dispatch_lines')->count())->toBe(0)->and(dispatchGuard($this->product))->toBe(['25.000', '20.000', '0.000']);
});

it('takes only its own line\'s reservation: another project\'s reserved supply is never taken (M-03; criterion 4)', function () {
    $sameProduct = $this->world->project(['WAREHOUSE'], '20');
    $rival = (int) DB::table('project_lines')->where('project_id', $sameProduct->id)->value('id');
    expect((int) DB::table('project_lines')->where('id', $rival)->value('product_id'))->toBe($this->product);
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '6');
    expect(ReservationWorld::reserve($this->actor, $rival, '5'))->toBe('COMMITTED');
    [$actor, $company, $mine, $unit, $rack] = [$this->actor, $this->company->id, $this->line, $this->unit, $this->rack];

    // Racing the owner of the reservation, this line takes the one unit AVAILABLE and no more.
    $results = Sessions::run([
        'owner' => static fn (): string => InvoiceWorld::hold(static fn (): string => DispatchWorld::dispatch($actor, $company, $rival, $unit, '5', [DispatchWorld::pick($lot, $rack, '5')]), 'the owner holds its locks'),
        'other' => static function () use ($actor, $company, $mine, $unit, $lot, $rack): string {
            Barrier::await('the owner holds its locks');

            return DispatchWorld::dispatch($actor, $company, $mine, $unit, '2', [DispatchWorld::pick($lot, $rack, '2')]);
        },
    ]);

    expect($results['owner'])->toBe('COMMITTED')->and($results['other'])->toContain('C-04')->and(dispatchGuard($this->product))->toBe(['1.000', '0.000', '0.000']);
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($lot, $this->rack, '1')]))->toBe('COMMITTED');
    dispatchClean();

    // Reserved supply untouched by a line that has none of it.
    $more = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '5');
    expect(ReservationWorld::reserve($this->actor, $rival, '5'))->toBe('COMMITTED')
        ->and(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($more, $this->rack, '1')]))->toContain('C-04');
});

it('leaves one of two dispatches racing for the last serialized unit (M-01; C-04; criterion 3)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $serial = StockWorld::serial($this->product, $lot, $this->rack);
    $other = $this->world->project(['WAREHOUSE'], '5');
    $rival = (int) DB::table('project_lines')->where('project_id', $other->id)->value('id');
    [$actor, $company, $line, $unit, $rack] = [$this->actor, $this->company->id, $this->line, $this->unit, $this->rack];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => DispatchWorld::dispatch($actor, $company, $line, $unit, '1', [DispatchWorld::pick($lot, $rack, '1', $serial)]), 'the first holds its locks'),
        'second' => static function () use ($actor, $company, $rival, $unit, $lot, $rack, $serial): string {
            Barrier::await('the first holds its locks');

            return DispatchWorld::dispatch($actor, $company, $rival, $unit, '1', [DispatchWorld::pick($lot, $rack, '1', $serial)]);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-04')
        ->and(dispatchGuard($this->product))->toBe(['0.000', '0.000', '0.000'])
        ->and(DB::table('serial_units')->where('id', $serial)->value('state'))->toBe('DISPATCHED');
});

it('leaves one of two dispatches racing for the last unit, in both orders, the loser REJECTED and never a negative balance (M-01; C-04; AC-16; criterion 3)', function (bool $swap) {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1');
    $other = $this->world->project(['WAREHOUSE'], '5');
    $rival = (int) DB::table('project_lines')->where('project_id', $other->id)->value('id');
    expect((int) DB::table('project_lines')->where('id', $rival)->value('product_id'))->toBe($this->product);
    [$actor, $company, $unit, $rack] = [$this->actor, $this->company->id, $this->unit, $this->rack];
    [$first, $second] = $swap ? [$rival, $this->line] : [$this->line, $rival];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => DispatchWorld::dispatch($actor, $company, $first, $unit, '1', [DispatchWorld::pick($lot, $rack, '1')]), 'the first holds its locks'),
        'second' => static function () use ($actor, $company, $second, $unit, $lot, $rack): string {
            Barrier::await('the first holds its locks');

            return DispatchWorld::dispatch($actor, $company, $second, $unit, '1', [DispatchWorld::pick($lot, $rack, '1')]);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-04')
        ->and(dispatchGuard($this->product))->toBe(['0.000', '0.000', '0.000']);
    dispatchClean();
})->with(['this line first' => false, 'the other first' => true]);

it('refuses a serial absent, unusable or at another lot or rack; one serial dispatched by two commands leaves one success (C-02; M-05; BR-INV-09; criterion 5)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '3');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $good = StockWorld::serial($this->product, $lot, $this->rack);
    $bad = StockWorld::serial($this->product, $lot, $this->rack, 'UNUSABLE');
    $elsewhere = ReceivingWorld::rack();

    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($lot, $this->rack, '1')]))->toContain('C-02')
        ->and(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($lot, $this->rack, '1', $bad)]))->toContain('C-02')
        ->and(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($lot, $elsewhere, '1', $good)]))->toContain('C-');
    [$actor, $company, $line, $unit, $rack] = [$this->actor, $this->company->id, $this->line, $this->unit, $this->rack];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => DispatchWorld::dispatch($actor, $company, $line, $unit, '1', [DispatchWorld::pick($lot, $rack, '1', $good)]), 'the first holds the serial'),
        'second' => static function () use ($actor, $company, $line, $unit, $lot, $rack, $good): string {
            Barrier::await('the first holds the serial');

            return DispatchWorld::dispatch($actor, $company, $line, $unit, '1', [DispatchWorld::pick($lot, $rack, '1', $good)]);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-02')
        ->and(DB::table('serial_units')->where('id', $good)->value('state'))->toBe('DISPATCHED');
    dispatchClean();
});

it('attributes 41,667 and 58,333 of a box of 12 for 100,000, the zeroing draw taking the residual (annex 10; BR-FIN-12; BR-FIN-13; criterion 6)', function () {
    $lot = StockWorld::opening($this->actor, $this->company, $this->product, $this->rack, '12', '100000.00');
    $other = $this->world->project(['WAREHOUSE'], '20');
    $rival = (int) DB::table('project_lines')->where('project_id', $other->id)->value('id');

    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '5', [DispatchWorld::pick($lot, $this->rack, '5')]))->toBe('COMMITTED')
        ->and(DispatchWorld::dispatch($this->actor, $this->company->id, $rival, $this->unit, '7', [DispatchWorld::pick($lot, $this->rack, '7')]))->toBe('COMMITTED');
    $hpp = fn (int $project): string => (string) DB::table('cost_attributions')->where('bearer_project_id', $project)->where('cost_class', 'HPP')->sum('amount');

    expect($hpp($this->demand['project']->id))->toBe('41667.00')->and($hpp($other->id))->toBe('58333.00')
        ->and(DB::table('lot_cost_balances as b')->where('lot_id', $lot)->value('remaining_cost'))->toBe('0.00');
    dispatchClean();
});

it('takes each unit\'s HPP once under two dispatches racing for one lot (LK-23; architecture challenge 18a; criterion 6)', function () {
    $lot = StockWorld::opening($this->actor, $this->company, $this->product, $this->rack, '12', '100000.00');
    $other = $this->world->project(['WAREHOUSE'], '20');
    $rival = (int) DB::table('project_lines')->where('project_id', $other->id)->value('id');
    [$actor, $company, $line, $unit, $rack] = [$this->actor, $this->company->id, $this->line, $this->unit, $this->rack];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => DispatchWorld::dispatch($actor, $company, $line, $unit, '5', [DispatchWorld::pick($lot, $rack, '5')]), 'the first draw holds the lot'),
        'second' => static function () use ($actor, $company, $rival, $unit, $lot, $rack): string {
            Barrier::await('the first draw holds the lot');

            return DispatchWorld::dispatch($actor, $company, $rival, $unit, '7', [DispatchWorld::pick($lot, $rack, '7')]);
        },
    ]);
    $hpp = fn (int $project): string => (string) DB::table('cost_attributions')->where('bearer_project_id', $project)->where('cost_class', 'HPP')->sum('amount');

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'COMMITTED'])
        ->and($hpp($this->demand['project']->id))->toBe('41667.00')->and($hpp($other->id))->toBe('58333.00')
        ->and(dispatchLotCostTotal($lot))->toBe('100000.00')
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('0.00');
    dispatchClean();
});

it('ends a cost correction and a dispatch of its lot with Σ attributed = the corrected cost in each timing (M-07; ROUTE-175; architecture challenge 2b; criterion 15)', function (string $timing) {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '12', '10000');
    $corrector = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'purchase.correct_cost'])->id;
    [$actor, $company, $line, $unit, $rack] = [$this->actor, $this->company->id, $this->line, $this->unit, $this->rack];
    $pick = [DispatchWorld::pick($lot, $rack, '2')];

    if ($timing === 'dispatched before') {
        expect(DispatchWorld::dispatch($actor, $company, $line, $unit, '2', $pick))->toBe('COMMITTED')
            ->and(DispatchWorld::correctLotCost($corrector, $lot, '132000'))->toBe('COMMITTED');
    }

    if ($timing === 'dispatch holds the lot') {
        // The correction derives its locks with no dispatch to see, waits, and restarts with this one's project.
        $results = Sessions::run([
            'dispatch' => static fn (): string => InvoiceWorld::hold(static fn (): string => DispatchWorld::dispatch($actor, $company, $line, $unit, '2', $pick), 'the dispatch holds the lot'),
            'correction' => static function () use ($corrector, $lot): string {
                Barrier::await('the dispatch holds the lot');

                return DispatchWorld::correctLotCost($corrector, $lot, '132000');
            },
        ]);
        expect($results)->toBe(['dispatch' => 'COMMITTED', 'correction' => 'COMMITTED']);
    }

    if ($timing === 'correction holds the lot') {
        $results = Sessions::run([
            'correction' => static fn (): string => InvoiceWorld::hold(static fn (): string => DispatchWorld::correctLotCost($corrector, $lot, '132000'), 'the correction holds the lot'),
            'dispatch' => static function () use ($actor, $company, $line, $unit, $pick): string {
                Barrier::await('the correction holds the lot');

                return DispatchWorld::dispatch($actor, $company, $line, $unit, '2', $pick);
            },
        ]);
        expect($results)->toBe(['correction' => 'COMMITTED', 'dispatch' => 'COMMITTED']);
    }

    $consumption = DispatchWorld::last($this->line)->consumption;

    expect(dispatchLotCostTotal($lot))->toBe('132000.00')
        ->and((string) DB::table('cost_attributions')->where('stock_consumption_id', $consumption)->sum('amount'))->toBe('22000.00')
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('110000.00');
    dispatchClean();
})->with(['dispatched before', 'dispatch holds the lot', 'correction holds the lot']);

it('carries another company\'s lot as one allocated consumption: source A, bearer B\'s project, reason and actor, HPP on B\'s project (annex 12; XL-1; C-28; L-44; criterion 7)', function () {
    $foreign = InvoiceWorld::make();
    $owner = ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id;
    $lot = StockWorld::lot($owner, $foreign->company, $this->product, $this->unit, $this->rack, '10', '12000');
    $allowed = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'stock.allocate_intercompany'])->id;

    expect(DispatchWorld::dispatch($allowed, $this->company->id, $this->line, $this->unit, '6', [DispatchWorld::pick($lot, $this->rack, '6')], 'Stok perusahaan sendiri habis'))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);

    expect($row->source_company_id)->toBe($foreign->company->id)->and($row->bearer_company_id)->toBe($this->company->id)->and($row->bearer_project_id)->toBe($this->demand['project']->id)
        ->and($row->allocation_reason)->toBe('Stok perusahaan sendiri habis')->and($row->allocation_authorized_by)->toBe($allowed)
        ->and(DB::table('stock_consumptions')->where('dispatch_line_id', $row->dispatch)->count())->toBe(1)
        ->and((string) DB::table('cost_attributions')->where('stock_consumption_id', $row->consumption)->where('cost_class', 'HPP')->sum('amount'))->toBe('72000.00')
        ->and(DB::table('lot_cost_balances as b')->where('lot_id', $lot)->first(['remaining_qty_base'])->remaining_qty_base)->toBe('4.000');
    dispatchClean();

    // Each side sees the draw as its own: B its cost with A masked, A the draw with B's project masked (PJ-05; PJ-06; SM-12; TM-25; TM-53).
    $figures = app(CostFigures::class);
    $bearer = Authorization::admin([$this->company->id], ['cost.view'])->id;
    $source = Authorization::admin([$foreign->company->id], ['cost.view'])->id;
    $own = $figures->allocations($bearer)[0];
    $theirs = $figures->allocations($source)[0];
    expect($figures->project($bearer, $this->demand['project']->id)['hpp'])->toBe('72000.00')
        ->and($own['amount'])->toBe('72000.00')->and($own['source_company_id'])->toBeNull()->and($own['reason'])->toBe('Stok perusahaan sendiri habis')
        ->and($theirs['amount'])->toBe('72000.00')->and($theirs['source_company_id'])->toBe($foreign->company->id)->and($theirs['bearer_company_id'])->toBeNull()->and($theirs['bearer_project_id'])->toBeNull()->and($theirs['reason'])->toBeNull()->and($theirs['authorized_by'])->toBeNull();

    // LG-07's signal and the Owner's review read the event: its authority class and the allocation it carried, and its reversal soon after.
    $event = DB::table('audit_events')->where('action', 'INV.GoodsDispatched')->sole();
    expect($event->authority_class)->toBe('ADM_PLUS')->and(json_decode($event->after, true)['allocated'])->toBeTrue();
    expect(DispatchWorld::restore($allowed, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($row->consumption, $this->rack, '6')]))->toBe('COMMITTED');
    expect(json_decode(DB::table('audit_events')->where('action', 'INV.DispatchReversed')->value('after'), true)['allocation_reversed'])->toBeTrue()
        ->and((string) DB::table('cost_attributions')->where('stock_consumption_id', $row->consumption)->sum('amount'))->toBe('0.00')
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('120000.00')
        ->and(DB::table('stock_lot_balances as b')->where('lot_id', $lot)->where('condition', 'USABLE')->value('qty'))->toBe('10.000');
    dispatchClean();
});

it('refuses and routes another company\'s lot without the authority, without a grant on the consuming company or without the reason, one event per affected company and nothing else (XL-1; AZ-08; SQ-23; UXS-22; criterion 8)', function () {
    $foreign = InvoiceWorld::make();
    $owner = ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id;
    $lot = StockWorld::lot($owner, $foreign->company, $this->product, $this->unit, $this->rack, '10');
    $allowed = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'stock.allocate_intercompany'])->id;
    $events = fn (int $company): int => DB::table('audit_events')->where('action', 'INV.AllocationRouted')->where('company_id', $company)->count();

    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($lot, $this->rack, '1')], 'Alasan'))->toContain('AZ-08')
        ->and($events($this->company->id))->toBe(1)->and($events($foreign->company->id))->toBe(1);
    expect(DispatchWorld::dispatch($allowed, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($lot, $this->rack, '1')]))->toContain('AZ-08')
        ->and($events($this->company->id))->toBe(2)->and($events($foreign->company->id))->toBe(2);
    expect(DB::table('dispatch_lines')->count())->toBe(0)->and(dispatchGuard($this->product))->toBe(['10.000', '0.000', '0.000']);
});

it('refuses a likely duplicate with its matches and commits it only with the actor\'s reason naming them; a match that appeared meanwhile refuses again (L-49; BD-13; UXS-15; criterion 9)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    $pick = [DispatchWorld::pick($lot, $this->rack, '2')];
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '2', $pick))->toBe('COMMITTED');
    $first = DB::table('stock_movements')->where('movement_type', 'DISPATCH')->value('public_id');

    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '2', $pick))->toContain('L-49');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '2', $pick, null, [$first], 'Pengiriman kedua hari ini'))->toBe('COMMITTED');
    expect(DB::table('audit_events')->where('action', 'INV.GoodsDispatchDuplicateConfirmed')->count())->toBe(1);

    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '2', $pick, null, [$first], 'Lagi'))->toContain('L-49')
        ->and(DB::table('dispatch_lines')->where('project_line_id', $this->line)->count())->toBe(2);
    dispatchClean();
});

it('restores units to their lot in the condition chosen at the contra of their HPP, the consumed reservation staying CONSUMED, a new one in the same command (CM-05; SF-RESTORE; L-07; criterion 10)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    expect(ReservationWorld::reserve($this->actor, $this->line, '5'))->toBe('COMMITTED');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '5', [DispatchWorld::pick($lot, $this->rack, '5')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);

    expect(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($row->consumption, $this->rack, '3'), DispatchWorld::item($row->consumption, $this->rack, '2', 'UNUSABLE')], '3'))->toBe('COMMITTED');

    expect(dispatchGuard($this->product))->toBe(['10.000', '3.000', '2.000'])->and(dispatchLineGuard($this->line))->toBe(['3.000', '0.000'])
        ->and(DB::table('reservations')->where('project_line_id', $this->line)->orderBy('id')->pluck('state')->all())->toBe(['CONSUMED', 'ACTIVE'])
        ->and(DB::table('stock_restorations')->where('stock_consumption_id', $row->consumption)->pluck('kind')->unique()->all())->toBe(['DISPATCH_REVERSAL'])
        ->and((string) DB::table('cost_attributions')->where('stock_consumption_id', $row->consumption)->sum('amount'))->toBe('0.00')
        ->and(DB::table('stock_consumption_balances as b')->where('stock_consumption_id', $row->consumption)->value('restored_qty_base'))->toBe('5.000');
    dispatchClean();
});

it('restores at most what is undelivered and open, a second restoration refused; a partial one its share, the zeroing one the residual (CM-06; PX-08; C-27; L-31; DATABASE §6.2; criteria 11, 12)', function () {
    $lot = StockWorld::opening($this->actor, $this->company, $this->product, $this->rack, '3', '100.00');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '3', [DispatchWorld::pick($lot, $this->rack, '3')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    $net = fn (): string => (string) DB::table('cost_attributions')->where('stock_consumption_id', $row->consumption)->sum('amount');

    expect(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($row->consumption, $this->rack, '4')]))->toContain('C-27');
    expect(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($row->consumption, $this->rack, '1')]))->toBe('COMMITTED');
    expect($net())->toBe('67.00');
    expect(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($row->consumption, $this->rack, '2')]))->toBe('COMMITTED');
    expect($net())->toBe('0.00')->and(DB::table('lot_cost_balances as b')->where('lot_id', $lot)->value('remaining_cost'))->toBe('100.00');
    expect(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($row->consumption, $this->rack, '1')]))->toContain('C-27');
    dispatchClean();
});

it('caps a restoration by net dispatched − net delivered, and refuses a likely duplicate unless its reason names the match (PX-08; C-30; L-49; BD-13)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '6', [DispatchWorld::pick($lot, $this->rack, '6')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    $one = [DispatchWorld::item($row->consumption, $this->rack, '1')];

    expect(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'UNDELIVERED_RETURN', $one))->toBe('COMMITTED');
    $first = DB::table('stock_movements')->where('movement_type', 'RESTORATION')->value('public_id');
    expect(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'UNDELIVERED_RETURN', $one))->toContain('L-49')
        ->and(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'UNDELIVERED_RETURN', $one, null, [$first], 'Kembali lagi siang ini'))->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'INV.DispatchReversalDuplicateConfirmed')->count())->toBe(1);
    dispatchClean();

    // A later dispatch of the same project line meets the same BD-13 key.
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '2', [DispatchWorld::pick($lot, $this->rack, '2')]))->toBe('COMMITTED');
    $later = DispatchWorld::last($this->line);
    expect(DispatchWorld::restore($this->actor, $this->company->id, $later->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($later->consumption, $this->rack, '1')]))->toContain('L-49');

    // Five delivered of the six out: what remains undelivered caps the return, though the consumption holds more.
    $courier = ReceivingWorld::actor($this->company, DeliveryWorld::CAPABILITIES)->id;
    expect(DeliveryWorld::record($courier, $this->line, [[$row->dispatch, '4'], [$later->dispatch, '1']], DeliveryWorld::proof($this->company)))->toBe('COMMITTED');
    expect(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($row->consumption, $this->rack, '2')], null, [$first], 'Kembali'))->toContain('C-30');
});

it('converts a restoration entered in the dispatch line\'s unit at its snapshot ratio (L-31; C-09; TL-03)', function () {
    $box = UnitFactory::new()->createOne()->id;
    ProductUnitFactory::new()->createOne(['product_id' => $this->product, 'unit_id' => $box, 'ratio_to_base' => 12, 'is_base' => false, 'is_active' => true]);
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '20');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $box, '1', [DispatchWorld::pick($lot, $this->rack, '12')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);

    expect(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [[...DispatchWorld::item($row->consumption, $this->rack, '1'), 'base_unit' => false]]))->toBe('COMMITTED');
    $back = DB::table('stock_restorations')->where('stock_consumption_id', $row->consumption)->sole();

    expect($back->qty_base)->toBe('12.000')->and((int) $back->ratio_to_base)->toBe(12)->and((int) $back->cited_ratio_to_base)->toBe(12)->and((int) $back->entered_unit_id)->toBe($box);
    dispatchClean();
});

it('refuses a serial against a consumption it already left, and the pages and the Surat Jalan name only what is still out (AX-10; AX-35; BR-DOC-04; ER-06)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '3');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    [$left, $stays, $spare] = [StockWorld::serial($this->product, $lot, $this->rack), StockWorld::serial($this->product, $lot, $this->rack), StockWorld::serial($this->product, $lot, $this->rack)];
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '2', [DispatchWorld::pick($lot, $this->rack, '1', $left), DispatchWorld::pick($lot, $this->rack, '1', $stays)]))->toBe('COMMITTED');
    $first = DispatchWorld::last($this->line);
    expect(DispatchWorld::restore($this->actor, $this->company->id, $first->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($first->consumption, $this->rack, '1', 'USABLE', $left)]))->toBe('COMMITTED');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($lot, $this->rack, '1', $left)]))->toBe('COMMITTED');

    // Dispatched again, on another consumption: the first holds a unit still open, but not this one.
    expect(DispatchWorld::restore($this->actor, $this->company->id, $first->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($first->consumption, $this->rack, '1', 'USABLE', $left)], null, DB::table('stock_movements')->where('movement_type', 'RESTORATION')->pluck('public_id')->all(), 'Lagi'))->toContain('C-02')
        ->and(DispatchWorld::substitute($this->actor, $this->company->id, $first->consumption, '1', $left, $lot, $this->rack, $spare, DispatchWorld::evidence($this->company)))->toContain('C-02');

    $number = fn (int $serial): string => (string) DB::table('serial_units')->where('id', $serial)->value('serial_number');
    $records = app(DispatchQueries::class)->screen($this->actor, $this->demand['project']->id, $this->company->id)['dispatches'];
    expect(array_column($records[1]['consumptions'][0]['serials'], 'serial'))->toBe([$number($stays)])
        ->and(array_column($records[0]['consumptions'][0]['serials'], 'serial'))->toBe([$number($left)]);

    $facts = app(DispatchSubjects::class)->facts(new DocumentSubject('DOC-03', 'STANDARD', $this->company->id, ['dispatch_movement_id' => $first->movement]));
    expect($facts->lines[0]['quantity'])->toBe('1')->and($facts->lines[0]['note'])->toBe('No. Seri: '.$number($stays));
    dispatchClean();
});

it('leaves one of two restorations of the same goods racing (C-27; architecture challenge 3b)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '2', [DispatchWorld::pick($lot, $this->rack, '2')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    [$actor, $company, $rack] = [$this->actor, $this->company->id, $this->rack];
    $dispatch = $row->dispatch;
    $consumption = $row->consumption;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => DispatchWorld::restore($actor, $company, $dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($consumption, $rack, '2')]), 'the first holds the consumption'),
        'second' => static function () use ($actor, $company, $dispatch, $consumption, $rack): string {
            Barrier::await('the first holds the consumption');

            return DispatchWorld::restore($actor, $company, $dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($consumption, $rack, '2')]);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-27')->and(dispatchGuard($this->product))->toBe(['10.000', '0.000', '0.000']);
    dispatchClean();
});

it('moves a consumption from the recorded serial to the one shipped, HPP re-attributed, the line and quantities unchanged; a shipped unit not in stock refused (CM-36; AX-35; C-27; C-02; criterion 13)', function () {
    $old = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '1000');
    $new = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '1500');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $recorded = StockWorld::serial($this->product, $old, $this->rack);
    $shipped = StockWorld::serial($this->product, $new, $this->rack);
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($old, $this->rack, '1', $recorded)]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    $evidence = DispatchWorld::evidence($this->company);
    $before = [dispatchGuard($this->product), dispatchLineGuard($this->line)];

    expect(DispatchWorld::substitute($this->actor, $this->company->id, $row->consumption, '1', $recorded, $new, ReceivingWorld::rack(), $shipped, $evidence))->toContain('C-');
    expect(DispatchWorld::substitute($this->actor, $this->company->id, $row->consumption, '1', $recorded, $new, $this->rack, $shipped, $evidence))->toBe('COMMITTED');

    $consumptions = DB::table('stock_consumptions')->where('dispatch_line_id', $row->dispatch)->orderBy('id')->get();
    expect($consumptions)->toHaveCount(2)->and($consumptions[1]->lot_id)->toBe($new)
        ->and(DB::table('stock_consumption_balances as b')->where('stock_consumption_id', $row->consumption)->value('substituted_qty_base'))->toBe('1.000')
        ->and(DB::table('serial_units')->where('id', $recorded)->value('state'))->toBe('IN_STOCK')->and(DB::table('serial_units')->where('id', $shipped)->value('state'))->toBe('DISPATCHED')
        ->and([dispatchGuard($this->product), dispatchLineGuard($this->line)])->toBe($before)
        ->and((string) DB::table('cost_attributions')->where('bearer_project_id', $this->demand['project']->id)->where('cost_class', 'HPP')->sum('amount'))->toBe('1500.00')
        ->and(DB::table('stock_restorations')->where('stock_consumption_id', $row->consumption)->value('kind'))->toBe('SUBSTITUTION')
        // The Surat Jalan of the shipment names the unit actually shipped; the movement is net zero, its evidence linked.
        ->and(app(DispatchSubjects::class)->facts(new DocumentSubject('DOC-03', 'STANDARD', $this->company->id, ['dispatch_movement_id' => $row->movement]))->lines[0]['note'])->toBe('No. Seri: '.DB::table('serial_units')->where('id', $shipped)->value('serial_number'))
        ->and(DB::table('evidence_links')->where('evidence_document_id', $evidence)->whereNotNull('stock_movement_id')->count())->toBe(1);
    dispatchClean();

    expect(DispatchWorld::substitute($this->actor, $this->company->id, $consumptions[1]->id, '1', $shipped, $old, $this->rack, $shipped, $evidence))->toContain('C-02');
});

it('needs its authority again to substitute onto another company\'s lot (XL-1; AZ-08)', function () {
    $own = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($own, $this->rack, '1')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    $foreign = InvoiceWorld::make();
    $lot = StockWorld::lot(ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id, $foreign->company, $this->product, $this->unit, $this->rack, '2');

    $evidence = DispatchWorld::evidence($this->company);
    $events = fn (int $company): int => DB::table('audit_events')->where('action', 'INV.AllocationRouted')->where('company_id', $company)->count();

    expect(DispatchWorld::substitute($this->actor, $this->company->id, $row->consumption, '1', null, $lot, $this->rack, null, $evidence, 'Alasan'))->toContain('AZ-08')
        ->and($events($this->company->id))->toBe(1)->and($events($foreign->company->id))->toBe(1);

    // With the authority the allocation comes with it, re-recorded by the lot's company; substituted back, it is reversed with its consumption (L-44).
    $allowed = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'stock.allocate_intercompany'])->id;
    expect(DispatchWorld::substitute($allowed, $this->company->id, $row->consumption, '1', null, $lot, $this->rack, null, $evidence))->toContain('AZ-08')
        ->and(DispatchWorld::substitute($allowed, $this->company->id, $row->consumption, '1', null, $lot, $this->rack, null, $evidence, 'Yang terkirim lot perusahaan lain'))->toBe('COMMITTED');
    $onto = DB::table('stock_consumptions')->where('dispatch_line_id', $row->dispatch)->orderByDesc('id')->first();
    expect($onto->lot_id)->toBe($lot)->and($onto->source_company_id)->toBe($foreign->company->id)->and($onto->allocation_reason)->toBe('Yang terkirim lot perusahaan lain')->and($onto->allocation_authorized_by)->toBe($allowed);
    dispatchClean();

    expect(DispatchWorld::substitute($allowed, $this->company->id, $onto->id, '1', null, $own, $this->rack, null, $evidence))->toBe('COMMITTED');
    $off = DB::table('stock_consumptions')->where('dispatch_line_id', $row->dispatch)->orderByDesc('id')->first();
    expect($off->source_company_id)->toBe($this->company->id)->and($off->allocation_reason)->toBeNull()->and($off->allocation_authorized_by)->toBeNull()
        ->and(dispatchLineGuard($this->line))->toBe(['0.000', '1.000'])
        ->and((string) DB::table('cost_attributions')->where('stock_consumption_id', $onto->id)->sum('amount'))->toBe('0.00');
    dispatchClean();
});

it('holds the guards by their CHECKs against violating writes, a dispatch recognizing no loss (C-02; C-03; C-04; C-08; C-09; C-24)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '3');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($lot, $this->rack, '1')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);

    expect(fn () => DB::table('stock_product_balances')->where('product_id', $this->product)->update(['reserved_qty' => '99']))->toThrow(QueryException::class, 'stock_product_balances_c04_available')
        ->and(fn () => DB::table('stock_lot_balances')->where('lot_id', $lot)->update(['qty' => '-1']))->toThrow(QueryException::class, 'stock_lot_balances_c03_not_negative')
        ->and(fn () => DB::table('dispatch_lines')->where('id', $row->dispatch)->update(['qty_base' => '7']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('serial_units')->where('id', StockWorld::serial($this->product, $lot, $this->rack))->update(['current_lot_id' => null]))->toThrow(QueryException::class, 'serial_units_c02_in_stock')
        ->and(fn () => DB::table('inventory_lots')->where('id', $lot)->update(['source_company_id' => InvoiceWorld::make()->company->id]))->toThrow(QueryException::class)
        ->and(DB::table('cost_attributions')->where('stock_consumption_id', $row->consumption)->where('cost_class', 'LOSS')->count())->toBe(0);
    dispatchClean();
});

it('lets a pending unexplained loss block no dispatch the normal guards allow (DIR-027; M-06; criterion 16)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    StockWorld::recognition($this->actor, $this->company, $this->product, $lot, $this->rack, '2', '10');

    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '8', [DispatchWorld::pick($lot, $this->rack, '8')]))->toBe('COMMITTED')
        ->and(dispatchGuard($this->product))->toBe(['0.000', '0.000', '0.000'])
        ->and(DB::table('unattributed_loss_cases')->value('pending_qty_base'))->toBe('2.000');
    dispatchClean();
});
