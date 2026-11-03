<?php

use App\Modules\FUL\Actions\ConfirmDropShip;
use App\Modules\FUL\Actions\ReturnDropShipToSupplier;
use App\Modules\PUR\Data\PurchaseEntry;
use App\Support\BusinessDate;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\DropshipWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\PurchaseWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReturnWorld;

// TL-05 (TASK-046; AX-08; AX-10; CM-14; CM-15; CM-27; L-18; L-40; BD-04; BD-18; C-02; C-08; C-25; C-27; C-29; C-30; C-46;
// FX-04's drop-ship reversals; criteria 1–9): a confirmation creates no warehouse event and attributes the line's actual
// cost once; partial confirmations commit until the cap; one shipment reference confirms once; serials ship once; the
// client's proof records the delivery in the same command; a reversal, a return to the warehouse and a return to the
// supplier settled by refund, replacement and unrecovered share each end in their stated state. The reconciliation suite
// runs after every case (tests/Pest.php).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = DropshipWorld::actor($this->company, [...ReturnWorld::CAPABILITIES, 'cases.close'])->id;
});

it('confirms with no receipt, dispatch, lot or stock change and attributes the line\'s cost once, by cumulative rounding (BR-INV-11; FS-05; L-18; C-25; criteria 1, 2)', function () {
    $w = DropshipWorld::make($this->world, '10', '3', '100000.01');
    $movements = DB::table('stock_movements')->count();

    expect(DropshipWorld::confirm($this->actor, $w, '1', 'SJ-1'))->toBe('COMMITTED')
        ->and(DropshipWorld::confirm($this->actor, $w, '2', 'SJ-2'))->toBe('COMMITTED');

    $lines = DB::table('dropship_confirmation_lines')->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    $guard = DropshipWorld::guard($w['purchase_line']);

    expect(DB::table('stock_movements')->count())->toBe($movements)->and(DB::table('inventory_lots')->count())->toBe(0)->and(DB::table('stock_movement_entries')->count())->toBe(0)
        ->and(DB::table('receiving_lines')->count())->toBe(0)->and(DB::table('dispatch_lines')->count())->toBe(0)
        // 300000.03 over 3 units: the first unit's share rounded to Rp1, the confirmation completing the line the cumulative rest — never a cent created or lost (annex 14; BR-FIN-13).
        ->and(DropshipWorld::cost($lines[0]))->toBe('100000.00')->and(DropshipWorld::cost($lines[1]))->toBe('200000.03')
        ->and(DB::table('cost_attributions')->where('dropship_confirmation_line_id', $lines[0])->where('cause', 'INITIAL')->count())->toBe(1)
        ->and((string) $guard->dropship_confirmed_net_qty_base)->toBe('3.000')->and((string) $guard->posted_goods_value)->toBe('300000.03')
        ->and(DropshipWorld::demand($w['line'])['confirmed'])->toBe('3.000')
        ->and(DB::table('evidence_links')->whereNotNull('dropship_confirmation_id')->count())->toBe(2);
});

it('replays one command_id with one effect (CI-05; criterion 3)', function () {
    $w = DropshipWorld::make($this->world);
    $command = (string) Str::uuid();
    $proof = DropshipWorld::proof($this->company->id);

    expect(DropshipWorld::confirm($this->actor, $w, '2', 'SJ-1', commandId: $command, evidence: $proof))->toBe('COMMITTED')
        ->and(DropshipWorld::confirm($this->actor, $w, '2', 'SJ-1', commandId: $command, evidence: $proof))->toBe('COMMITTED')
        ->and(DB::table('dropship_confirmations')->count())->toBe(1)->and(DropshipWorld::demand($w['line'])['confirmed'])->toBe('2.000');
});

it('commits partial confirmations until the purchase line\'s cap, then REJECTED with the remainder (C-29; PX-03; M-35; criterion 3)', function () {
    $w = DropshipWorld::make($this->world, '20', '10');

    expect(DropshipWorld::confirm($this->actor, $w, '6', 'SJ-1'))->toBe('COMMITTED')
        ->and(DropshipWorld::confirm($this->actor, $w, '4', 'SJ-2'))->toBe('COMMITTED')
        ->and(DropshipWorld::confirm($this->actor, $w, '1', 'SJ-3'))->toBe('REJECTED C-29')
        ->and((string) DropshipWorld::guard($w['purchase_line'])->dropship_confirmed_net_qty_base)->toBe('10.000');
});

it('refuses above the demand\'s remainder (C-30; criterion 3)', function () {
    $w = DropshipWorld::make($this->world, '5', '10');

    expect(DropshipWorld::confirm($this->actor, $w, '5', 'SJ-1'))->toBe('COMMITTED')
        ->and(DropshipWorld::confirm($this->actor, $w, '1', 'SJ-2'))->toBe('REJECTED C-30');
});

it('answers a second confirmation of one supplier shipment reference CONFLICT (BD-04; C-49; MSG-04; criterion 3)', function () {
    $w = DropshipWorld::make($this->world);

    expect(DropshipWorld::confirm($this->actor, $w, '2', 'SJ-1'))->toBe('COMMITTED')
        ->and(DropshipWorld::confirm($this->actor, $w, '1', 'SJ-1'))->toBe('CONFLICT BD-04')
        ->and(DB::table('dropship_confirmations')->count())->toBe(1);
});

it('takes each serial once, DROPSHIPPED and never IN_STOCK; one already drop-shipped, or a count not its serials, refused (BD-18; C-02; criterion 4)', function () {
    $w = DropshipWorld::make($this->world, serialized: true);

    expect(DropshipWorld::confirm($this->actor, $w, '2', 'SJ-1', ['SN-A', 'sn-b ']))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->whereIn('serial_key', ['sn-a', 'sn-b'])->pluck('state')->unique()->all())->toBe(['DROPSHIPPED'])
        ->and(DB::table('dropship_confirmation_lines')->whereNotNull('serial_unit_id')->count())->toBe(2)
        ->and(DropshipWorld::confirm($this->actor, $w, '1', 'SJ-2', ['SN-A']))->toBe('REJECTED C-02')
        ->and(DropshipWorld::confirm($this->actor, $w, '2', 'SJ-3', ['SN-C']))->toBe('REJECTED C-02')
        ->and(DropshipWorld::demand($w['line'])['confirmed'])->toBe('2.000');
});

it('records the delivery in the same command when the client\'s proof arrives with it, its rows as any delivery\'s (L-40; AX-08; criterion 5)', function () {
    $w = DropshipWorld::make($this->world);
    $proof = DropshipWorld::proof($this->company->id);

    expect(DropshipWorld::confirm($this->actor, $w, '3', 'SJ-1', delivery: ['recipient' => 'Bu Sari', 'condition' => null, 'proof' => $proof]))->toBe('COMMITTED');

    $line = DB::table('delivery_lines')->sole();

    expect(DB::table('deliveries')->count())->toBe(1)->and($line->dropship_confirmation_line_id)->toBe(DropshipWorld::last()->lines[0])->and($line->dispatch_line_id)->toBeNull()
        ->and(DropshipWorld::demand($w['line']))->toBe(['confirmed' => '3.000', 'delivered' => '3.000'])
        ->and(DB::table('evidence_links')->whereNotNull('delivery_id')->value('evidence_document_id'))->toBe($proof);
});

it('reverses a confirmation recorded in error once with its reason: HPP contra\'d, the line\'s confirmed and posted lowered, the serial NEVER_RECEIVED (CM-27; C-27; C-59; criterion 6)', function () {
    $w = DropshipWorld::make($this->world, serialized: true);
    DropshipWorld::confirm($this->actor, $w, '2', 'SJ-1', ['SN-A', 'SN-B']);
    $confirmation = DropshipWorld::last();
    $command = (string) Str::uuid();
    $item = [['line' => $confirmation->lines[0], 'quantity' => '1']];

    expect(DropshipWorld::reverse($this->actor, $w['project'], $confirmation->id, $item, $command))->toBe('COMMITTED')
        ->and(DropshipWorld::reverse($this->actor, $w['project'], $confirmation->id, $item, $command))->toBe('COMMITTED')
        ->and(DropshipWorld::reverse($this->actor, $w['project'], $confirmation->id, $item))->toBe('REJECTED C-27');

    $guard = DropshipWorld::guard($w['purchase_line']);

    expect(DropshipWorld::cost($confirmation->lines[0]))->toBe('0.00')->and(DropshipWorld::cost($confirmation->lines[1]))->toBe('100000.00')
        ->and((string) $guard->dropship_confirmed_net_qty_base)->toBe('1.000')->and((string) $guard->posted_goods_value)->toBe('100000.00')
        ->and(DropshipWorld::demand($w['line'])['confirmed'])->toBe('1.000')
        ->and(DB::table('serial_units')->where('serial_key', 'sn-a')->value('state'))->toBe('NEVER_RECEIVED')
        ->and(DB::table('stock_restorations')->where('kind', 'DROPSHIP_CONFIRMATION_REVERSAL')->whereNull('stock_movement_id')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'FUL.DropShipConfirmationReversed')->count())->toBe(1);
});

it('refuses to reverse what the client is recorded to have received: that delivery is corrected first (CM-27; CM-08; L-32)', function () {
    $w = DropshipWorld::make($this->world);
    DropshipWorld::confirm($this->actor, $w, '2', 'SJ-1', delivery: ['recipient' => 'Bu Sari', 'condition' => null, 'proof' => DropshipWorld::proof($this->company->id)]);
    $confirmation = DropshipWorld::last();

    expect(DropshipWorld::reverse($this->actor, $w['project'], $confirmation->id, [['line' => $confirmation->lines[0], 'quantity' => '1']]))->toBe('REJECTED C-27')
        ->and(DropshipWorld::demand($w['line']))->toBe(['confirmed' => '2.000', 'delivered' => '2.000']);
});

it('returns to the warehouse into one DROPSHIP_RETURN lot whose cost is exactly the HPP contra, the net delivered lowered with it (CM-14; C-08; PX-08; criterion 7)', function () {
    $w = DropshipWorld::make($this->world, '10', '4', '100000.01');
    DropshipWorld::confirm($this->actor, $w, '4', 'SJ-1', delivery: ['recipient' => 'Bu Sari', 'condition' => null, 'proof' => DropshipWorld::proof($this->company->id)]);
    $confirmation = DropshipWorld::last();
    $rack = ReceivingWorld::rack();
    $before = DropshipWorld::cost($confirmation->lines[0]);

    expect(DropshipWorld::returned($this->actor, $w['project'], $confirmation->id, ReturnDropShipToSupplier::WAREHOUSE, [['line' => $confirmation->lines[0], 'quantity' => '3']], $rack))->toBe('COMMITTED');

    $lot = DB::table('inventory_lots')->sole();
    $basis = (string) DB::table('lot_cost_entries')->where('lot_id', $lot->id)->where('entry_kind', 'DROPSHIP_RETURN_BASIS')->sum('amount');
    $after = DropshipWorld::cost($confirmation->lines[0]);

    expect($lot->acquisition_kind)->toBe('DROPSHIP_RETURN')->and((string) $lot->acquired_qty_base)->toBe('3.000')
        ->and((string) BigDecimal::of($after)->plus($basis)->toScale(2))->toBe($before)->and($after)->toBe('100000.04')->and($basis)->toBe('300000.00')
        ->and((string) DB::table('stock_product_balances')->where('product_id', $w['product'])->value('on_hand_qty'))->toBe('3.000')
        ->and(DropshipWorld::demand($w['line']))->toBe(['confirmed' => '1.000', 'delivered' => '1.000'])
        ->and(DB::table('correction_cases')->where('id', DropshipWorld::lastCase())->value('case_kind'))->toBe('DROPSHIP_RETURN')
        ->and(DB::table('stock_movements')->where('movement_type', 'DROPSHIP_RETURN_RECEIPT')->count())->toBe(1);
});

it('returns a serial to the warehouse into stock at its rack (CM-14; C-02)', function () {
    $w = DropshipWorld::make($this->world, serialized: true);
    DropshipWorld::confirm($this->actor, $w, '1', 'SJ-1', ['SN-A']);
    $confirmation = DropshipWorld::last();
    $rack = ReceivingWorld::rack();

    expect(DropshipWorld::returned($this->actor, $w['project'], $confirmation->id, ReturnDropShipToSupplier::WAREHOUSE, [['line' => $confirmation->lines[0], 'quantity' => '1']], $rack, 'UNUSABLE'))->toBe('COMMITTED');

    $serial = DB::table('serial_units')->where('serial_key', 'sn-a')->first();

    expect($serial->state)->toBe('IN_STOCK')->and($serial->current_location_id)->toBe($rack)->and($serial->current_condition)->toBe('UNUSABLE')
        ->and($serial->current_lot_id)->toBe(DB::table('inventory_lots')->value('id'));
});

it('returns several lines to the warehouse in one command, each its own lot, the later line\'s cascade holding the earlier lot\'s cost guard (CM-14; C-08; SQ-20)', function () {
    $w = DropshipWorld::make($this->world, serialized: true);
    DropshipWorld::confirm($this->actor, $w, '2', 'SJ-1', ['SN-A', 'SN-B']);
    $confirmation = DropshipWorld::last();
    $items = [['line' => $confirmation->lines[0], 'quantity' => '1'], ['line' => $confirmation->lines[1], 'quantity' => '1']];

    expect(DropshipWorld::returned($this->actor, $w['project'], $confirmation->id, ReturnDropShipToSupplier::WAREHOUSE, $items, ReceivingWorld::rack()))->toBe('COMMITTED')
        ->and(DB::table('inventory_lots')->where('acquisition_kind', 'DROPSHIP_RETURN')->count())->toBe(2)
        ->and(DB::table('serial_units')->whereIn('serial_key', ['sn-a', 'sn-b'])->pluck('state')->unique()->values()->all())->toBe(['IN_STOCK'])
        ->and((string) DB::table('lot_cost_entries')->where('entry_kind', 'DROPSHIP_RETURN_BASIS')->sum('amount'))->toBe('200000.00')
        ->and(DropshipWorld::demand($w['line'])['confirmed'])->toBe('0.000');
});

it('refuses two purchase lines whose sum is above their one demand line\'s remainder, with the remainder (C-30)', function () {
    $w = DropshipWorld::make($this->world, '3', '10');
    // A second purchase of two direct-to-client lines, both for the same demand line, through ROUTE-171's own action.
    $line = ['handle' => null, 'kind' => 'GOODS', 'product' => $w['product'], 'line' => $w['line'], 'mode' => 'DROPSHIP', 'quantity' => '5', 'unit' => $w['unit'], 'price' => '100000', 'description' => 'Barang kirim langsung', 'taxes' => []];
    expect(PurchaseWorld::save(PurchaseWorld::actor($this->company)->id, new PurchaseEntry($this->company->id, $w['project']->id, PurchaseWorld::supplier()->id, BusinessDate::today(), false, null, [$line, $line], [])))->toBe('COMMITTED');
    $purchase = PurchaseWorld::latest($this->company->id);
    $ids = DB::table('purchase_lines')->where('purchase_id', $purchase->id)->orderBy('line_no')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    $product = DB::table('products')->where('id', $w['product'])->first(['is_serialized', 'quantity_scale']);
    $lines = array_map(fn (int $id): array => ['line' => $id, 'quantity' => '2', 'serials' => [], 'serialized' => (bool) $product->is_serialized, 'scale' => (int) $product->quantity_scale], $ids);
    $outcome = app(ConfirmDropShip::class)->run((string) Str::uuid(), $this->actor, $w['project'], $purchase->id, 'SJ-1', BusinessDate::today(), false, $lines, DropshipWorld::proof($this->company->id), null, null, ['lines' => $lines]);

    expect(InvoiceWorld::word($outcome))->toBe('REJECTED C-30')->and($outcome->state['remainder'] ?? null)->toBe('1.000');
});

it('returns to the supplier movement-less under a purchase-return case, settled by credit, replacement and unrecovered share, the case closing only on the equation (CM-15; C-46; L-38; criterion 8)', function () {
    $w = DropshipWorld::make($this->world, '10', '4');
    DropshipWorld::confirm($this->actor, $w, '4', 'SJ-1', delivery: ['recipient' => 'Bu Sari', 'condition' => null, 'proof' => DropshipWorld::proof($this->company->id)]);
    $confirmation = DropshipWorld::last();
    $movements = DB::table('stock_movements')->count();

    expect(DropshipWorld::returned($this->actor, $w['project'], $confirmation->id, ReturnDropShipToSupplier::SUPPLIER, [['line' => $confirmation->lines[0], 'quantity' => '3']]))->toBe('COMMITTED');

    $case = DropshipWorld::lastCase();

    expect(DB::table('stock_movements')->count())->toBe($movements)->and(DB::table('inventory_lots')->count())->toBe(0)
        ->and(DB::table('correction_cases')->where('id', $case)->first(['case_kind', 'causal_project_id']))->toEqual((object) ['case_kind' => 'PURCHASE_RETURN', 'causal_project_id' => $w['project']->id])
        ->and(ReturnWorld::figures($case)['returned'])->toBe('300000.00')
        ->and(DropshipWorld::cost($confirmation->lines[0], 'SUPPLIER_RETURN'))->toBe('300000.00')->and(DropshipWorld::cost($confirmation->lines[0]))->toBe('100000.00')
        ->and(DropshipWorld::demand($w['line']))->toBe(['confirmed' => '1.000', 'delivered' => '1.000'])
        ->and(DB::table('serial_units')->count())->toBe(0);

    // The unrecovered share, a loss once on the causal project; its correction takes part of it back (L-38; C-24).
    expect(ReturnWorld::outcome($this->actor, $case, 'UNRECOVERED', amount: '50000.00'))->toBe('COMMITTED')
        ->and(ReturnWorld::outcome($this->actor, $case, 'UNRECOVERED', amount: '20000.00', correction: true))->toBe('COMMITTED')
        ->and(DropshipWorld::cost($confirmation->lines[0], 'LOSS'))->toBe('30000.00')
        ->and(DB::table('cost_attributions')->where('cost_class', 'LOSS')->where('cause', 'RETURN_SETTLEMENT')->distinct()->pluck('bearer_project_id')->all())->toBe([$w['project']->id])
        ->and(ReturnWorld::figures($case)['unrecovered'])->toBe('30000.00')
        // A credit of the purchase value still unpaid (DATABASE §3.1), then the replacement the line now expects.
        ->and(ReturnWorld::outcome($this->actor, $case, 'CREDIT', amount: '70000.00', evidence: ReturnWorld::credit($this->company->id)))->toBe('COMMITTED')
        ->and(ReturnWorld::outcome($this->actor, $case, 'REPLACEMENT', qty: '2'))->toBe('COMMITTED')
        ->and((string) DropshipWorld::guard($w['purchase_line'])->replacement_expected_qty_base)->toBe('2.000')
        ->and(ReturnWorld::close($this->actor, $case))->not->toBe('COMMITTED');

    // The replacement confirmation carries the case's open basis over the units still expected, counted only on the replacement cap (C-59; PX-03).
    expect(DropshipWorld::confirm($this->actor, $w, '1', 'SJ-GANTI-1', case: $case))->toBe('COMMITTED');
    $first = DropshipWorld::last()->lines[0];

    expect(DropshipWorld::cost($first))->toBe('100000.00')->and(ReturnWorld::figures($case)['replaced'])->toBe('100000.00')
        ->and(DropshipWorld::confirm($this->actor, $w, '1', 'SJ-GANTI-2', case: $case))->toBe('COMMITTED')
        ->and(DropshipWorld::confirm($this->actor, $w, '1', 'SJ-GANTI-3', case: $case))->toBe('REJECTED C-29');

    $guard = DropshipWorld::guard($w['purchase_line']);

    expect(ReturnWorld::figures($case))->toMatchArray(['returned' => '300000.00', 'refunded' => '70000.00', 'replaced' => '200000.00', 'unrecovered' => '30000.00'])
        ->and((string) $guard->replacement_received_net_qty_base)->toBe('2.000')->and((string) $guard->dropship_confirmed_net_qty_base)->toBe('4.000')
        ->and(DropshipWorld::demand($w['line'])['confirmed'])->toBe('3.000')
        ->and(ReturnWorld::close($this->actor, $case))->toBe('COMMITTED');
});

it('reverses a replacement confirmed in error, its case\'s replaced value and the line\'s replacement taken back (CM-27; C-59; C-46)', function () {
    $w = DropshipWorld::make($this->world, '10', '4');
    DropshipWorld::confirm($this->actor, $w, '2', 'SJ-1');
    $confirmation = DropshipWorld::last();
    DropshipWorld::returned($this->actor, $w['project'], $confirmation->id, ReturnDropShipToSupplier::SUPPLIER, [['line' => $confirmation->lines[0], 'quantity' => '2']]);
    $case = DropshipWorld::lastCase();
    ReturnWorld::outcome($this->actor, $case, 'REPLACEMENT', qty: '2');
    DropshipWorld::confirm($this->actor, $w, '2', 'SJ-GANTI', case: $case);
    $replacement = DropshipWorld::last();

    expect(ReturnWorld::figures($case)['replaced'])->toBe('200000.00')
        ->and(DropshipWorld::reverse($this->actor, $w['project'], $replacement->id, [['line' => $replacement->lines[0], 'quantity' => '1']]))->toBe('COMMITTED')
        ->and(ReturnWorld::figures($case)['replaced'])->toBe('100000.00')
        ->and((string) DropshipWorld::guard($w['purchase_line'])->replacement_received_net_qty_base)->toBe('1.000')
        ->and(DropshipWorld::demand($w['line'])['confirmed'])->toBe('1.000');
});

it('refuses a line of another purchase, a warehouse line and a closed project (C-14; BR-INV-11; L-11)', function () {
    $w = DropshipWorld::make($this->world);
    $other = DropshipWorld::make($this->world);

    expect(DropshipWorld::confirm($this->actor, [...$w, 'purchase_line' => $other['purchase_line']], '1'))->toBe('REJECTED C-14');

    DB::table('purchase_lines')->where('id', $w['purchase_line'])->update(['delivery_mode' => 'WAREHOUSE']);

    expect(DropshipWorld::confirm($this->actor, $w, '1', 'SJ-9'))->toBe('REJECTED BR-INV-11');

    DB::table('projects')->where('id', $other['project']->id)->update(['state' => 'COMPLETED_NORMAL']);

    expect(DropshipWorld::confirm($this->actor, $other, '1', 'SJ-10'))->toBe('REJECTED L-11');
});

it('confirms a likely duplicate return with its reason, under the acknowledged action (L-49; BD-13; INT-015)', function () {
    $w = DropshipWorld::make($this->world);
    DropshipWorld::confirm($this->actor, $w, '4', 'SJ-1');
    $confirmation = DropshipWorld::last();
    $item = [['line' => $confirmation->lines[0], 'quantity' => '1']];
    $first = (string) Str::uuid();

    expect(DropshipWorld::returned($this->actor, $w['project'], $confirmation->id, ReturnDropShipToSupplier::SUPPLIER, $item, commandId: $first))->toBe('COMMITTED')
        ->and(DropshipWorld::returned($this->actor, $w['project'], $confirmation->id, ReturnDropShipToSupplier::SUPPLIER, $item))->toBe('REJECTED L-49')
        ->and(DropshipWorld::returned($this->actor, $w['project'], $confirmation->id, ReturnDropShipToSupplier::SUPPLIER, $item, acknowledged: [$first], reason: 'Dua retur terpisah'))->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'FUL.DropShipReturnDuplicateConfirmed')->count())->toBe(1)
        ->and(BusinessDate::today())->toBeString();
});
