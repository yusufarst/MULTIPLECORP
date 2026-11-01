<?php

use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
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

function lineGuard(int $line): array
{
    $row = DB::table('project_line_balances as b')->where('project_line_id', $line)->first(['reserved_qty_base', 'dispatched_net_qty_base']);

    return [$row->reserved_qty_base, $row->dispatched_net_qty_base];
}

function clean(): void
{
    expect(StockWorld::ledgerDifferences())->toBe([])
        // C-28's DQ: a consumption across companies carries its reason and authorizing actor.
        ->and(DB::table('stock_consumptions')->whereColumn('source_company_id', '<>', 'bearer_company_id')->where(fn ($where) => $where->whereNull('allocation_reason')->orWhereNull('allocation_authorized_by'))->count())->toBe(0);
}

it('dispatches the reserved units as one physical effect: 10, 5, 2 becomes 5, 0, 2 with 3 available, the reservation CONSUMED (annex 7; BR-INV-02; AC-22; criterion 1)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    StockWorld::conditionChange($this->actor, $this->company, $this->product, $lot, $this->rack, '2');
    expect(ReservationWorld::reserve($this->actor, $this->line, '5'))->toBe('COMMITTED');

    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '5', [DispatchWorld::pick($lot, $this->rack, '5')]))->toBe('COMMITTED');

    expect(dispatchGuard($this->product))->toBe(['5.000', '0.000', '2.000'])
        ->and(lineGuard($this->line))->toBe(['0.000', '5.000'])
        ->and(DB::table('reservations')->where('project_line_id', $this->line)->value('state'))->toBe('CONSUMED')
        ->and(DB::table('reservation_events')->where('kind', 'CONSUME')->value('qty_delta_base'))->toBe('-5.000');
    $dispatch = DB::table('dispatch_lines')->where('project_line_id', $this->line)->sole();
    expect($dispatch->reserved_qty_base)->toBe('5.000')->and($dispatch->unreserved_qty_base)->toBe('0.000')->and($dispatch->reservation_id)->not->toBeNull();
    clean();
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
    expect(dispatchGuard($this->product))->toBe(['3.000', '0.000', '0.000'])->and(lineGuard($this->line))->toBe(['0.000', '7.000']);
    clean();
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
    $other = DispatchWorld::demand($this->world);
    DB::table('project_lines')->where('id', $other['line'])->value('id');
    $sameProduct = $this->world->project(['WAREHOUSE'], '20');
    $rival = DB::table('project_lines')->where('project_id', $sameProduct->id)->first();
    $lot = StockWorld::lot($this->actor, $this->company, (int) $rival->product_id, $this->unit, $this->rack, '5');
    expect(ReservationWorld::reserve($this->actor, $rival->id, '5'))->toBe('COMMITTED');
    $mine = (int) DB::table('project_lines')->where('project_id', $this->demand['project']->id)->value('id');

    if ((int) DB::table('project_lines')->where('id', $mine)->value('product_id') === (int) $rival->product_id) {
        expect(DispatchWorld::dispatch($this->actor, $this->company->id, $mine, $this->unit, '1', [DispatchWorld::pick($lot, $this->rack, '1')]))->toContain('C-04');
    }

    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $rival->id, $this->unit, '5', [DispatchWorld::pick($lot, $this->rack, '5')]))->toBe('COMMITTED');
    clean();
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
    clean();
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
    clean();
});

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
    clean();
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
        ->and($events($this->company->id))->toBe(2);
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
    clean();
});

it('restores units to their lot in the condition chosen at the contra of their HPP, the consumed reservation staying CONSUMED, a new one in the same command (CM-05; SF-RESTORE; L-07; criterion 10)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    expect(ReservationWorld::reserve($this->actor, $this->line, '5'))->toBe('COMMITTED');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '5', [DispatchWorld::pick($lot, $this->rack, '5')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);

    expect(DispatchWorld::restore($this->actor, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($row->consumption, $this->rack, '3'), DispatchWorld::item($row->consumption, $this->rack, '2', 'UNUSABLE')], '3'))->toBe('COMMITTED');

    expect(dispatchGuard($this->product))->toBe(['10.000', '3.000', '2.000'])->and(lineGuard($this->line))->toBe(['3.000', '0.000'])
        ->and(DB::table('reservations')->where('project_line_id', $this->line)->orderBy('id')->pluck('state')->all())->toBe(['CONSUMED', 'ACTIVE'])
        ->and(DB::table('stock_restorations')->where('stock_consumption_id', $row->consumption)->pluck('kind')->unique()->all())->toBe(['DISPATCH_REVERSAL'])
        ->and((string) DB::table('cost_attributions')->where('stock_consumption_id', $row->consumption)->sum('amount'))->toBe('0.00')
        ->and(DB::table('stock_consumption_balances as b')->where('stock_consumption_id', $row->consumption)->value('restored_qty_base'))->toBe('5.000');
    clean();
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
    clean();
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
    clean();
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
    $before = [dispatchGuard($this->product), lineGuard($this->line)];

    expect(DispatchWorld::substitute($this->actor, $this->company->id, $row->consumption, '1', $recorded, $new, ReceivingWorld::rack(), $shipped, $evidence))->toContain('C-');
    expect(DispatchWorld::substitute($this->actor, $this->company->id, $row->consumption, '1', $recorded, $new, $this->rack, $shipped, $evidence))->toBe('COMMITTED');

    $consumptions = DB::table('stock_consumptions')->where('dispatch_line_id', $row->dispatch)->orderBy('id')->get();
    expect($consumptions)->toHaveCount(2)->and($consumptions[1]->lot_id)->toBe($new)
        ->and(DB::table('stock_consumption_balances as b')->where('stock_consumption_id', $row->consumption)->value('substituted_qty_base'))->toBe('1.000')
        ->and(DB::table('serial_units')->where('id', $recorded)->value('state'))->toBe('IN_STOCK')->and(DB::table('serial_units')->where('id', $shipped)->value('state'))->toBe('DISPATCHED')
        ->and([dispatchGuard($this->product), lineGuard($this->line)])->toBe($before)
        ->and((string) DB::table('cost_attributions')->where('bearer_project_id', $this->demand['project']->id)->where('cost_class', 'HPP')->sum('amount'))->toBe('1500.00')
        ->and(DB::table('stock_restorations')->where('stock_consumption_id', $row->consumption)->value('kind'))->toBe('SUBSTITUTION');
    clean();

    expect(DispatchWorld::substitute($this->actor, $this->company->id, $consumptions[1]->id, '1', $shipped, $old, $this->rack, $shipped, $evidence))->toContain('C-02');
});

it('needs its authority again to substitute onto another company\'s lot (XL-1; AZ-08)', function () {
    $own = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($own, $this->rack, '1')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    $foreign = InvoiceWorld::make();
    $lot = StockWorld::lot(ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id, $foreign->company, $this->product, $this->unit, $this->rack, '2');

    expect(DispatchWorld::substitute($this->actor, $this->company->id, $row->consumption, '1', null, $lot, $this->rack, null, DispatchWorld::evidence($this->company), 'Alasan'))->toContain('AZ-08');
});

it('lets a pending unexplained loss block no dispatch the normal guards allow (DIR-027; M-06; criterion 16)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack);
    StockWorld::recognition($this->actor, $this->company, $this->product, $lot, $this->rack, '2', '10');

    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '8', [DispatchWorld::pick($lot, $this->rack, '8')]))->toBe('COMMITTED')
        ->and(dispatchGuard($this->product))->toBe(['0.000', '0.000', '0.000'])
        ->and(DB::table('unattributed_loss_cases')->value('pending_qty_base'))->toBe('2.000');
});
