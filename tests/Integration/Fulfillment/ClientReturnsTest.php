<?php

use App\Modules\FUL\Actions\ReturnDropShipToSupplier;
use Brick\Math\BigDecimal;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\ClientReturnWorld;
use Tests\Support\CostWorld;
use Tests\Support\DeliveryWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\DropshipWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReturnWorld;
use Tests\Support\StockWorld;

// TL-05 (TASK-048; WF-FUL-05; SF-RESTORE; AX-10; AX-36; PX-08; L-07; L-31; L-44; CM-10–CM-15; CM-32; CM-37; C-27; C-30; C-55; annex 16): delivered goods taken back from the client once, on their original lot in the condition they came back in, at the consumption's cost, net delivered lowered and the delivery record kept, the case listing what is left; a restoration or a return recorded in error undone exactly by its linked reversal; the reconciliation suite after each.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, array_values(array_unique([...ClientReturnWorld::CAPABILITIES, ...ReturnWorld::CAPABILITIES, ...AdjustmentWorld::CAPABILITIES])))->id;
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->line = $this->demand['line'];
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
    $this->deliveryProof = DeliveryWorld::proof($this->company);
    $this->proof = ClientReturnWorld::proof($this->company);
});

function returnsClean(): void
{
    expect(StockWorld::ledgerDifferences())->toBe([])
        ->and(CostWorld::differences())->toBe([]);
}

/** A lot of `stock` at 1,000 each, `shipped` dispatched from it and `delivered` of them received by the client: the shipment. */
function clientDelivered(object $test, string $shipped, string $delivered, string $stock = '10'): object
{
    $test->lot = StockWorld::lot($test->actor, $test->company, $test->product, $test->unit, $test->rack, $stock, '1000');
    expect(DispatchWorld::dispatch($test->actor, $test->company->id, $test->line, $test->unit, $shipped, [DispatchWorld::pick($test->lot, $test->rack, $shipped)]))->toBe('COMMITTED');
    $shipment = DispatchWorld::last($test->line);
    expect(DeliveryWorld::record($test->actor, $test->line, [[$shipment->dispatch, $delivered]], $test->deliveryProof))->toBe('COMMITTED');

    return $shipment;
}

/** A held quantity moved by `delta`, as the balance reads it. */
function heldMoved(string $held, string $delta): string
{
    return (string) BigDecimal::of($held)->plus($delta)->toScale(3);
}

function clientReturned(object $test, object $shipment, string $qty, string $condition = 'USABLE', ?string $reserve = null): string
{
    return ClientReturnWorld::restore($test->actor, $test->company->id, $shipment->dispatch, [DispatchWorld::item($shipment->consumption, $test->rack, $qty, $condition)], $test->proof, $reserve);
}

it('takes back at most net delivered, lowering it and net dispatched, the delivery record kept; the units it took never come back again by an undelivered return (PX-08; C-30; architecture challenge 3b; criteria 1, 3)', function () {
    $shipment = clientDelivered($this, '5', '3');
    $recorded = (string) DB::table('delivery_lines')->sum('qty_base');

    expect(clientReturned($this, $shipment, '4'))->toContain('C-30')
        ->and(clientReturned($this, $shipment, '3'))->toBe('COMMITTED')
        ->and(DeliveryWorld::delivered($this->line))->toBe('0.000')
        ->and(ClientReturnWorld::dispatched($this->line))->toBe('2.000')
        ->and((string) DB::table('delivery_lines')->sum('qty_base'))->toBe($recorded)
        ->and(DB::table('deliveries')->count())->toBe(1)
        ->and(ClientReturnWorld::restored($shipment->consumption))->toBe('3.000')
        // Two of the five never reached the client: only those come back by an undelivered return.
        ->and(DispatchWorld::restore($this->actor, $this->company->id, $shipment->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($shipment->consumption, $this->rack, '3')]))->toContain('C-27')
        ->and(DispatchWorld::restore($this->actor, $this->company->id, $shipment->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($shipment->consumption, $this->rack, '2')]))->toBe('COMMITTED')
        ->and(ClientReturnWorld::restored($shipment->consumption))->toBe('5.000')
        ->and(clientReturned($this, $shipment, '1'))->not->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'INV.ClientReturnRecorded')->count())->toBe(1);
    returnsClean();
});

it('restores a usable unit into usable stock and a damaged one into UNUSABLE, both on the original lot, each at its share of the consumption\'s HPP (SF-RESTORE; BR-INV-06; L-31; criterion 2)', function () {
    $shipment = clientDelivered($this, '3', '3');
    $usable = ClientReturnWorld::held($this->lot, $this->rack);

    expect(clientReturned($this, $shipment, '1'))->toBe('COMMITTED')
        ->and(clientReturned($this, $shipment, '2', 'UNUSABLE'))->toBe('COMMITTED')
        ->and(ClientReturnWorld::held($this->lot, $this->rack))->toBe(heldMoved($usable, '1'))
        ->and(ClientReturnWorld::held($this->lot, $this->rack, 'UNUSABLE'))->toBe('2.000')
        ->and(DB::table('stock_movement_entries')->where('movement_type', 'RESTORATION')->distinct()->pluck('lot_id')->all())->toBe([$this->lot])
        ->and(CostWorld::rows($shipment->consumption))->toBe(['HPP INITIAL 3000.00', 'HPP RESTORATION_CONTRA -1000.00', 'HPP RESTORATION_CONTRA -2000.00'])
        ->and(DB::table('stock_restorations')->where('kind', 'SALES_RETURN')->where('after_delivery', true)->count())->toBe(2);
    returnsClean();
});

it('opens one case per lot with the reason and evidence, naming the returning project and the source lot, the demand and the damaged units\' outcome its open steps (SF-CASE; XL-3; ADMIN_FLOW §11; criteria 4, 5)', function () {
    $shipment = clientDelivered($this, '3', '3');

    expect(clientReturned($this, $shipment, '1', 'UNUSABLE'))->toBe('COMMITTED');
    $case = ClientReturnWorld::last();

    expect(DB::table('correction_cases')->where('id', $case->case)->first(['case_kind', 'state', 'reason', 'causal_project_id', 'source_lot_id']))
        ->toEqual((object) ['case_kind' => 'SALES_RETURN', 'state' => 'OPEN', 'reason' => 'Klien mengembalikan barang', 'causal_project_id' => $this->demand['project']->id, 'source_lot_id' => $this->lot])
        ->and(ClientReturnWorld::steps($case->case))->toBe(['demand', 'damaged_outcome'])
        ->and(DB::table('evidence_links')->where('evidence_document_id', $this->proof)->where('correction_case_id', $case->case)->exists())->toBeTrue();
    returnsClean();
});

it('leaves an issued invoice unchanged, listing its correction as the case\'s open step, and closes the case by its own command (BR-XD-01; CM-12; BR-CR-04; architecture challenge 4a; criterion 4)', function () {
    $shipment = clientDelivered($this, '2', '2');
    $invoice = $this->world->issued($this->demand['project'], '2000.00');
    $before = DB::table('invoices')->where('id', $invoice)->first();

    expect(clientReturned($this, $shipment, '1'))->toBe('COMMITTED');
    $case = ClientReturnWorld::last();

    expect(DB::table('invoices')->where('id', $invoice)->first())->toEqual($before)
        ->and(ClientReturnWorld::steps($case->case))->toBe(['demand', 'invoice_correction'])
        ->and(ReturnWorld::close($this->actor, $case->case))->toBe('COMMITTED')
        ->and(DB::table('correction_cases')->where('id', $case->case)->value('state'))->toBe('CLOSED');
    returnsClean();
});

it('never reactivates the consumed reservation: the offer makes a new one of the usable units with `stock.reserve`, never above them (L-07; IP-19; BR-RSV-03; criterion 3)', function () {
    $shipment = clientDelivered($this, '3', '3');
    $consumed = DB::table('reservations')->where('state', '<>', 'ACTIVE')->pluck('id')->all();

    expect(clientReturned($this, $shipment, '2', 'USABLE', '3'))->toContain('BR-RSV-03')
        ->and(clientReturned($this, $shipment, '2', 'USABLE', '2'))->toBe('COMMITTED')
        ->and(DB::table('reservations')->where('state', '<>', 'ACTIVE')->pluck('id')->all())->toBe($consumed)
        ->and((string) DB::table('reservations')->where('project_line_id', $this->line)->where('state', 'ACTIVE')->value('remaining_qty_base'))->toBe('2.000');
    returnsClean();
});

it('charges a client-damaged unit\'s later disposal once to the returning project through its case (CM-11; SF-LOSS; annex 16; GAP-028; criterion 5)', function () {
    $shipment = clientDelivered($this, '2', '2');
    expect(clientReturned($this, $shipment, '1', 'UNUSABLE'))->toBe('COMMITTED');
    $case = ClientReturnWorld::last();

    expect(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($this->lot, $this->rack, '1', 'UNUSABLE')], evidence: [AdjustmentWorld::evidence()]), 'DISPOSAL'))->toBe('COMMITTED');
    $loss = DB::table('stock_consumptions')->where('kind', 'LOSS')->sole();

    expect((int) $loss->correction_case_id)->toBe($case->case)
        ->and((int) $loss->bearer_project_id)->toBe($this->demand['project']->id)
        ->and(DB::table('stock_consumptions')->where('kind', 'LOSS')->count())->toBe(1);
    returnsClean();
});

it('returns a completed project to Aktif through SF-REVAL (L-11; CM-32; criterion 6)', function () {
    $shipment = clientDelivered($this, '2', '2');
    DB::table('projects')->where('id', $this->demand['project']->id)->update(['state' => 'COMPLETED_NORMAL']);

    expect(clientReturned($this, $shipment, '1'))->toBe('COMMITTED')
        ->and(DB::table('projects')->where('id', $this->demand['project']->id)->value('state'))->toBe('ACTIVE')
        ->and(DB::table('project_state_transitions')->where('project_id', $this->demand['project']->id)->where('transition_kind', 'REVALIDATE')->count())->toBe(1);
    returnsClean();
});

it('refuses a likely duplicate return until the actor confirms it with a reason, and answers one command_id replayed once (L-49; BD-13; CI-05; criterion 8)', function () {
    $shipment = clientDelivered($this, '4', '4');
    $command = (string) Str::uuid();
    $item = [DispatchWorld::item($shipment->consumption, $this->rack, '1')];

    expect(ClientReturnWorld::restore($this->actor, $this->company->id, $shipment->dispatch, $item, $this->proof, commandId: $command))->toBe('COMMITTED')
        ->and(ClientReturnWorld::restore($this->actor, $this->company->id, $shipment->dispatch, $item, $this->proof, commandId: $command))->toBe('COMMITTED')
        ->and(DB::table('stock_restorations')->where('kind', 'SALES_RETURN')->count())->toBe(1);

    $match = (string) DB::table('stock_movements')->where('id', ClientReturnWorld::last()->movement)->value('public_id');

    expect(ClientReturnWorld::restore($this->actor, $this->company->id, $shipment->dispatch, $item, $this->proof))->toContain('L-49')
        ->and(ClientReturnWorld::restore($this->actor, $this->company->id, $shipment->dispatch, $item, $this->proof, acknowledged: [$match], duplicateReason: 'Dua kardus berbeda'))->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'INV.ClientReturnDuplicateConfirmed')->count())->toBe(1);
    returnsClean();
});

it('reverses a client\'s return in error within what it still stands at, re-establishing the consumption, its cost, net dispatched and net delivered, never a loss (CM-37; AX-36; C-55; criterion 7)', function () {
    $shipment = clientDelivered($this, '3', '3');
    expect(clientReturned($this, $shipment, '2'))->toBe('COMMITTED');
    $return = ClientReturnWorld::last();
    $usable = ClientReturnWorld::held($this->lot, $this->rack);

    expect(ClientReturnWorld::reverse($this->actor, $this->company->id, $return->movement, [ClientReturnWorld::undo($return->restoration, '3')]))->toContain('C-55')
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, $return->movement, [ClientReturnWorld::undo($return->restoration, '1')]))->toBe('COMMITTED')
        ->and(DeliveryWorld::delivered($this->line))->toBe('2.000')
        ->and(ClientReturnWorld::dispatched($this->line))->toBe('2.000')
        ->and(ClientReturnWorld::restored($shipment->consumption))->toBe('1.000')
        ->and(ClientReturnWorld::held($this->lot, $this->rack))->toBe(heldMoved($usable, '-1'))
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, $return->movement, [ClientReturnWorld::undo($return->restoration, '1')]))->toBe('COMMITTED')
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, $return->movement, [ClientReturnWorld::undo($return->restoration, '1')]))->toContain('C-55')
        ->and(DeliveryWorld::delivered($this->line))->toBe('3.000')
        ->and(CostWorld::rows($shipment->consumption))->toBe(['HPP INITIAL 3000.00', 'HPP RESTORATION_CONTRA -2000.00', 'HPP RESTORATION_CONTRA 1000.00', 'HPP RESTORATION_CONTRA 1000.00'])
        ->and(DB::table('stock_movements')->where('movement_type', 'MOVEMENT_REVERSAL')->count())->toBe(2)
        ->and(DB::table('stock_movements')->whereIn('movement_type', ['LOSS', 'ADJUSTMENT_DECREASE'])->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'INV.ReturnReversed')->count())->toBe(2);
    returnsClean();
});

it('refuses a reversal whose units are no longer where the return put them (C-03; AX-36)', function () {
    $shipment = clientDelivered($this, '1', '1', '1');
    expect(clientReturned($this, $shipment, '1'))->toBe('COMMITTED');
    $return = ClientReturnWorld::last();
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($this->lot, $this->rack, '1')], evidence: [AdjustmentWorld::evidence()])))->toBe('COMMITTED');

    expect(ClientReturnWorld::reverse($this->actor, $this->company->id, $return->movement, [ClientReturnWorld::undo($return->restoration, '1')]))->toContain('C-03')
        ->and(ClientReturnWorld::restored($shipment->consumption))->toBe('1.000');
    returnsClean();
});

it('commits a reversal that leaves usable supply below the reservations only with the actor\'s cuts (IP-20; C-06; criterion 7)', function () {
    $shipment = clientDelivered($this, '3', '3', '3');
    expect(clientReturned($this, $shipment, '2', 'USABLE', '2'))->toBe('COMMITTED');
    $return = ClientReturnWorld::last();
    $reservation = (int) DB::table('reservations')->where('project_line_id', $this->line)->where('state', 'ACTIVE')->value('id');
    $undo = [ClientReturnWorld::undo($return->restoration, '1')];

    expect(ClientReturnWorld::reverse($this->actor, $this->company->id, $return->movement, $undo))->toContain('C-06')
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, $return->movement, $undo, [$reservation => '1']))->toBe('COMMITTED')
        ->and((string) DB::table('reservations')->where('id', $reservation)->value('remaining_qty_base'))->toBe('1.000');
    returnsClean();
});

it('reverses an undelivered return recorded in error, the units out on the shipment again (CM-06; CM-37)', function () {
    $this->lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '5', '1000');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '3', [DispatchWorld::pick($this->lot, $this->rack, '3')]))->toBe('COMMITTED');
    $shipment = DispatchWorld::last($this->line);
    expect(DispatchWorld::restore($this->actor, $this->company->id, $shipment->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($shipment->consumption, $this->rack, '2')]))->toBe('COMMITTED');
    $restoration = DB::table('stock_restorations')->where('kind', 'UNDELIVERED_RETURN')->sole();

    expect(ClientReturnWorld::reverse($this->actor, $this->company->id, (int) $restoration->stock_movement_id, [ClientReturnWorld::undo((int) $restoration->id, '2')]))->toBe('COMMITTED')
        ->and(ClientReturnWorld::dispatched($this->line))->toBe('3.000')
        ->and(ClientReturnWorld::restored($shipment->consumption))->toBe('0.000');
    returnsClean();
});

it('reverses a purchase return in error into the rack and condition chosen, its cost back on the lot and off the case, within what still stands returned (CM-37; C-55; criterion 7)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '5', '1000');
    expect(ReturnWorld::returnUnits($this->actor, $lot, [AdjustmentWorld::pick($lot, $this->rack, '2')]))->toBe('COMMITTED');
    $case = ReturnWorld::lastCase();
    $consumption = DB::table('stock_consumptions')->where('kind', 'PURCHASE_RETURN')->sole();
    $held = ClientReturnWorld::held($lot, $this->rack);

    expect(ClientReturnWorld::reverse($this->actor, $this->company->id, (int) $consumption->stock_movement_id, [ClientReturnWorld::unreturn((int) $consumption->id, '3', $this->rack)]))->toContain('C-55')
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, (int) $consumption->stock_movement_id, [ClientReturnWorld::unreturn((int) $consumption->id, '1', $this->rack)]))->toBe('COMMITTED')
        ->and(ClientReturnWorld::held($lot, $this->rack))->toBe(heldMoved($held, '1'))
        ->and(ReturnWorld::figures($case)['returned'])->toBe('1000.00')
        ->and(DB::table('stock_restorations')->where('kind', 'RETURN_ERROR_REVERSAL')->count())->toBe(1)
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, (int) $consumption->stock_movement_id, [ClientReturnWorld::unreturn((int) $consumption->id, '2', $this->rack)]))->toContain('C-55');
    returnsClean();
});

it('undoes a drop-ship supplier return in error once and exactly with one DROPSHIP_ERROR_REVERSAL (DATABASE §5.7; CM-37; C-55)', function () {
    $actor = DropshipWorld::actor($this->company, [...ReturnWorld::CAPABILITIES, 'cases.close', 'stock.reverse'])->id;
    $w = DropshipWorld::make($this->world, '10', '4');
    DropshipWorld::confirm($actor, $w, '4', 'SJ-1', delivery: ['recipient' => 'Bu Sari', 'condition' => null, 'proof' => DropshipWorld::proof($this->company->id)]);
    $confirmation = DropshipWorld::last();
    expect(DropshipWorld::returned($actor, $w['project'], $confirmation->id, ReturnDropShipToSupplier::SUPPLIER, [['line' => $confirmation->lines[0], 'quantity' => '3']]))->toBe('COMMITTED');
    $case = DropshipWorld::lastCase();
    $restoration = (int) DB::table('stock_restorations')->where('kind', 'DROPSHIP_SUPPLIER_RETURN')->value('id');

    expect(ClientReturnWorld::reverseDropship($actor, $this->company->id, $confirmation->id, $restoration))->toBe('COMMITTED')
        ->and(DropshipWorld::demand($w['line']))->toBe(['confirmed' => '4.000', 'delivered' => '4.000'])
        ->and(ReturnWorld::figures($case)['returned'])->toBe('0.00')
        ->and(DB::table('stock_restorations')->where('kind', 'DROPSHIP_ERROR_REVERSAL')->where('reverses_restoration_id', $restoration)->count())->toBe(1)
        ->and(ClientReturnWorld::reverseDropship($actor, $this->company->id, $confirmation->id, $restoration))->toContain('C-55');
    returnsClean();
});

it('refuses a reversal its line\'s own reservations leave no room for, committing it once the actor cuts them (C-05; C-06; IP-20; criterion 7)', function () {
    $demand = DispatchWorld::demand($this->world, '3');
    $lot = StockWorld::lot($this->actor, $this->company, $demand['product'], $demand['unit'], $this->rack, '10', '1000');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $demand['line'], $demand['unit'], '3', [DispatchWorld::pick($lot, $this->rack, '3')]))->toBe('COMMITTED');
    $shipment = DispatchWorld::last($demand['line']);
    expect(DeliveryWorld::record($this->actor, $demand['line'], [[$shipment->dispatch, '3']], $this->deliveryProof))->toBe('COMMITTED')
        ->and(ClientReturnWorld::restore($this->actor, $this->company->id, $shipment->dispatch, [DispatchWorld::item($shipment->consumption, $this->rack, '2')], $this->proof, '2'))->toBe('COMMITTED');
    $return = ClientReturnWorld::last();
    $reservation = (int) DB::table('reservations')->where('project_line_id', $demand['line'])->where('state', 'ACTIVE')->value('id');
    $undo = [ClientReturnWorld::undo($return->restoration, '1')];

    expect(ClientReturnWorld::reverse($this->actor, $this->company->id, $return->movement, $undo))->toContain('C-06')
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, $return->movement, $undo, [$reservation => '1']))->toBe('COMMITTED')
        ->and(DB::table('project_line_balances')->where('project_line_id', $demand['line'])->first(['reserved_qty_base', 'dispatched_net_qty_base']))
        ->toEqual((object) ['reserved_qty_base' => '1.000', 'dispatched_net_qty_base' => '2.000']);
    returnsClean();
});

it('refuses to take back a damaged return its case already disposed of, though another damaged unit of the lot sits at its place (CM-11; CM-37; C-55)', function () {
    $shipment = clientDelivered($this, '2', '2');
    expect(clientReturned($this, $shipment, '1', 'UNUSABLE'))->toBe('COMMITTED');
    $first = ClientReturnWorld::last();
    expect(clientReturned($this, $shipment, '1', 'UNUSABLE', null))->toContain('L-49');
    expect(ClientReturnWorld::restore($this->actor, $this->company->id, $shipment->dispatch, [DispatchWorld::item($shipment->consumption, $this->rack, '1', 'UNUSABLE')], $this->proof, acknowledged: [(string) DB::table('stock_movements')->where('id', $first->movement)->value('public_id')], duplicateReason: 'Unit kedua'))->toBe('COMMITTED')
        ->and(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($this->lot, $this->rack, '1', 'UNUSABLE')], evidence: [AdjustmentWorld::evidence()]), 'DISPOSAL', $first->case))->toBe('COMMITTED')
        ->and(ClientReturnWorld::held($this->lot, $this->rack, 'UNUSABLE'))->toBe('1.000')
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, $first->movement, [ClientReturnWorld::undo($first->restoration, '1')]))->toContain('C-55');
    returnsClean();
});

it('refuses to take back a purchase return its supplier\'s outcomes already settled: a credit above what would stay returned, a replacement declared above it (C-46; C-29; CM-37)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '5', '1000');
    expect(ReturnWorld::returnUnits($this->actor, $lot, [AdjustmentWorld::pick($lot, $this->rack, '2')]))->toBe('COMMITTED');
    $case = ReturnWorld::lastCase();
    $consumption = DB::table('stock_consumptions')->where('kind', 'PURCHASE_RETURN')->sole();
    $undo = [ClientReturnWorld::unreturn((int) $consumption->id, '1', $this->rack)];

    expect(ReturnWorld::outcome($this->actor, $case, 'REPLACEMENT', qty: '2'))->toBe('COMMITTED')
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, (int) $consumption->stock_movement_id, $undo))->toContain('C-29')
        ->and(ReturnWorld::outcome($this->actor, $case, 'REPLACEMENT', qty: '2', correction: true))->toBe('COMMITTED')
        ->and(ReturnWorld::outcome($this->actor, $case, 'CREDIT', amount: '2000.00', evidence: ReturnWorld::credit($this->company->id)))->toBe('COMMITTED')
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, (int) $consumption->stock_movement_id, $undo))->toContain('C-46')
        ->and(DB::table('stock_restorations')->where('kind', 'RETURN_ERROR_REVERSAL')->count())->toBe(0);
    returnsClean();
});

it('reopens a closed client\'s return case its restoration\'s reversal reaches, audited (CM-37; BR-CR-04; L-06)', function () {
    $shipment = clientDelivered($this, '2', '2');
    expect(clientReturned($this, $shipment, '1'))->toBe('COMMITTED');
    $return = ClientReturnWorld::last();
    expect(ReturnWorld::close($this->actor, $return->case))->toBe('COMMITTED')
        ->and(ClientReturnWorld::reverse($this->actor, $this->company->id, $return->movement, [ClientReturnWorld::undo($return->restoration, '1')]))->toBe('COMMITTED')
        ->and(DB::table('correction_cases')->where('id', $return->case)->value('state'))->toBe('OPEN')
        ->and(DB::table('audit_events')->where('action', 'OPS.CorrectionCaseReopened')->where('entity_id', $return->case)->count())->toBe(1);
    returnsClean();
});

it('takes a reversed undelivered return\'s credit off the closure it answered, so no more is delivered than stood (CM-27; CM-37; PX-08)', function () {
    $this->lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '1000');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '5', [DispatchWorld::pick($this->lot, $this->rack, '5')]))->toBe('COMMITTED');
    $shipment = DispatchWorld::last($this->line);
    expect(DeliveryWorld::close($this->actor, $this->line, $shipment->dispatch, 'REFUSED', '2'))->toBe('COMMITTED')
        ->and(DispatchWorld::restore($this->actor, $this->company->id, $shipment->dispatch, 'UNDELIVERED_RETURN', [DispatchWorld::item($shipment->consumption, $this->rack, '2')]))->toBe('COMMITTED');
    $restoration = DB::table('stock_restorations')->where('kind', 'UNDELIVERED_RETURN')->sole();

    expect(ClientReturnWorld::reverse($this->actor, $this->company->id, (int) $restoration->stock_movement_id, [ClientReturnWorld::undo((int) $restoration->id, '2')]))->toBe('COMMITTED')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '4']], $this->deliveryProof))->toContain('C-30')
        ->and(DeliveryWorld::record($this->actor, $this->line, [[$shipment->dispatch, '3']], $this->deliveryProof))->toBe('COMMITTED');
    returnsClean();
});

it('FX-01 16: takes one delivered unit of a 50,000 lot back damaged into UNUSABLE with its 50,000 contra and a case naming the project, its disposal charging 50,000 to that project once (BR-INV-13; DIR-024 D-3; annex 16)', function () {
    $priced = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '50000');
    expect(DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($priced, $this->rack, '1')]))->toBe('COMMITTED');
    $unit = DispatchWorld::last($this->line);
    expect(DeliveryWorld::record($this->actor, $this->line, [[$unit->dispatch, '1']], $this->deliveryProof))->toBe('COMMITTED')
        ->and(ClientReturnWorld::restore($this->actor, $this->company->id, $unit->dispatch, [DispatchWorld::item($unit->consumption, $this->rack, '1', 'UNUSABLE')], $this->proof))->toBe('COMMITTED');
    $case = ClientReturnWorld::last();

    expect(ClientReturnWorld::held($priced, $this->rack, 'UNUSABLE'))->toBe('1.000')
        ->and(CostWorld::rows($unit->consumption))->toBe(['HPP INITIAL 50000.00', 'HPP RESTORATION_CONTRA -50000.00'])
        ->and(DB::table('correction_cases')->where('id', $case->case)->first(['state', 'causal_project_id', 'source_lot_id']))
        ->toEqual((object) ['state' => 'OPEN', 'causal_project_id' => $this->demand['project']->id, 'source_lot_id' => $priced])
        ->and(AdjustmentWorld::adjust($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($priced, $this->rack, '1', 'UNUSABLE')], evidence: [AdjustmentWorld::evidence()]), 'DISPOSAL', $case->case))->toBe('COMMITTED')
        ->and((string) DB::table('cost_attributions as a')->join('stock_consumptions as c', 'c.id', '=', 'a.stock_consumption_id')->where('c.kind', 'LOSS')->where('c.bearer_project_id', $this->demand['project']->id)->sum('a.amount'))->toBe('50000.00');
    returnsClean();
});

it('FX-03 3: takes another company\'s unit back damaged onto its own lot with the HPP contra and the allocation reversed with it, net allocation 0 (L-44; C-28; XL-3; BR-INV-05)', function () {
    $foreign = InvoiceWorld::make();
    $owner = ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id;
    $lot = StockWorld::lot($owner, $foreign->company, $this->product, $this->unit, $this->rack, '5', '50000');
    $allowed = ReceivingWorld::actor($this->company, array_values(array_unique([...ClientReturnWorld::CAPABILITIES, 'stock.allocate_intercompany'])))->id;
    expect(DispatchWorld::dispatch($allowed, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($lot, $this->rack, '1')], 'Stok perusahaan sendiri habis'))->toBe('COMMITTED');
    $shipment = DispatchWorld::last($this->line);
    expect(DeliveryWorld::record($allowed, $this->line, [[$shipment->dispatch, '1']], $this->deliveryProof))->toBe('COMMITTED')
        ->and(ClientReturnWorld::restore($allowed, $this->company->id, $shipment->dispatch, [DispatchWorld::item($shipment->consumption, $this->rack, '1', 'UNUSABLE')], $this->proof))->toBe('COMMITTED');
    $case = ClientReturnWorld::last();

    expect((int) $shipment->source_company_id)->toBe($foreign->company->id)
        ->and(ClientReturnWorld::held($lot, $this->rack, 'UNUSABLE'))->toBe('1.000')
        ->and(CostWorld::rows($shipment->consumption))->toBe(['HPP INITIAL 50000.00', 'HPP RESTORATION_CONTRA -50000.00'])
        ->and(DB::table('correction_cases')->where('id', $case->case)->first(['company_id', 'causal_project_id', 'source_lot_id']))
        ->toEqual((object) ['company_id' => $this->company->id, 'causal_project_id' => $this->demand['project']->id, 'source_lot_id' => $lot]);
    returnsClean();
});

it('changes only what it corrects on a cancelled project: the return commits and the project stays cancelled (BR-CR-05; L-11; criterion 6)', function () {
    $shipment = clientDelivered($this, '2', '2');
    DB::table('projects')->where('id', $this->demand['project']->id)->update(['state' => 'CANCELLED']);

    expect(clientReturned($this, $shipment, '1'))->toBe('COMMITTED')
        ->and(DB::table('projects')->where('id', $this->demand['project']->id)->value('state'))->toBe('CANCELLED')
        ->and(DB::table('project_state_transitions')->where('project_id', $this->demand['project']->id)->where('transition_kind', 'REVALIDATE')->count())->toBe(0)
        ->and(DeliveryWorld::delivered($this->line))->toBe('1.000');
    returnsClean();
});
