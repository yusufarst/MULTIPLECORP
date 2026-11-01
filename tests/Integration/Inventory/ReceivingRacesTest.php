<?php

use App\Modules\INV\Data\ReceiptEntry;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\StockMovementEntry;
use App\Modules\PUR\Actions\CancelPurchase;
use App\Modules\PUR\Actions\ClosePurchaseRemainder;
use App\Modules\PUR\Actions\CorrectPurchaseCost;
use App\Modules\PUR\Models\Purchase;
use App\Support\BusinessDate;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\CostWorld;
use Tests\Support\DatabaseTruncation;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\PurchaseWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\Refusal;

// TL-05 (TASK-039; AX-04; AX-05; M-05; M-30; M-35; M-36; F2 of DIR-101) on real PostgreSQL sessions: quantity, serial, lot and cost integrity under concurrency — partial receipts until the cap, duplicate deliveries, a receipt racing its line's closure and cancellation, one serial received twice, first use of a scope; AX-05 under §5's protocol with a re-spread over a consumed lot; the guard CHECKs by violating writes after a real receipt; FX-04's corrections between partial receipts end to end; the reconciliation suite after every scenario.

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
    $this->actor = ReceivingWorld::actor($this->company, ['cost.view', 'purchase.manage', 'purchase.cancel', 'purchase.close_remainder', 'purchase.correct_cost'])->id;
    $goods = Projects::product();
    $this->goods = $goods;
    $this->product = $goods['product']->id;
    $this->unit = $goods['base']->id;
    $this->rack = ReceivingWorld::rack();
    $this->today = BusinessDate::today();
});

function receivingRaceDifferences(): array
{
    $found = [];

    foreach (['stock_product_balances', 'stock_scope_balances', 'stock_lot_balances', 'purchase_line_balances', 'lot_cost_balances', 'purchase_charge_balances', 'purchase_charge_share_balances'] as $family) {
        $named = Families::named($family);

        if ($named !== null) {
            $found = [...$found, ...(new Reconciler)->differences(DB::connection(), $named)];
        }
    }

    return $found;
}

it('M-35: concurrent partial receipts of one line commit until the cap, the other REJECTED with the remainder (C-29; PX-03; criterion 8)', function () {
    $purchase = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '10');
    $line = ReceivingWorld::line($purchase);
    $args = [$this->actor, $this->company->id, $purchase->id, $line->id, $this->unit, $this->rack];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => ReceivingWorld::receive(...$args, usable: '6'), 'the first holds its locks'),
        'second' => static function () use ($args): string {
            Barrier::await('the first holds its locks');

            return ReceivingWorld::receive(...$args, usable: '6');
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-29')
        ->and(ReceivingWorld::balance($line->id)->normal_received_net_qty_base)->toBe('6.000')
        ->and(DB::table('inventory_lots')->count())->toBe(1)->and(DB::table('stock_movements')->where('movement_type', 'RECEIPT')->count())->toBe(1)
        ->and(receivingRaceDifferences())->toBe([]);
});

it('M-35: two ORIGINAL receipts of one delivery reference leave one, the other CONFLICT (BD-01; C-49; criterion 8)', function () {
    $purchase = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '10');
    $line = ReceivingWorld::line($purchase);
    $args = [$this->actor, $this->company->id, $purchase->id, $line->id, $this->unit, $this->rack];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => ReceivingWorld::receive(...$args, usable: '2', reference: 'SJ-77'), 'the first holds its locks'),
        'second' => static function () use ($args): string {
            Barrier::await('the first holds its locks');

            return ReceivingWorld::receive(...$args, usable: '2', reference: 'SJ-77');
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('BD-01')
        ->and(ReceivingWorld::balance($line->id)->normal_received_net_qty_base)->toBe('2.000')
        ->and(receivingRaceDifferences())->toBe([]);
});

it('M-36: a receipt holding its locks first leaves the closure refused against the new remainder (C-29; challenge 2a; criterion 10)', function () {
    $purchase = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '10');
    $line = ReceivingWorld::line($purchase);
    $args = [$this->actor, $this->company->id, $purchase->id, $line->id, $this->unit, $this->rack];
    [$actor, $purchaseId, $lineId, $today] = [$this->actor, $purchase->id, $line->id, $this->today];

    $results = Sessions::run([
        'receipt' => static fn (): string => InvoiceWorld::hold(static fn (): string => ReceivingWorld::receive(...$args, usable: '6'), 'the receipt holds its locks'),
        'closure' => static function () use ($actor, $purchaseId, $lineId, $today): string {
            Barrier::await('the receipt holds its locks');

            return InvoiceWorld::word(app(ClosePurchaseRemainder::class)->run((string) Str::uuid(), $actor, Purchase::query()->findOrFail($purchaseId), [$lineId => '6'], 'Tutup sisa', $today, false));
        },
    ]);
    $balance = ReceivingWorld::balance($line->id);

    expect($results['receipt'])->toBe('COMMITTED')->and($results['closure'])->toContain('C-29')
        ->and($balance->normal_received_net_qty_base)->toBe('6.000')->and($balance->closed_qty_base)->toBe('0.000')->and(receivingRaceDifferences())->toBe([]);
});

it('M-36: a closure holding its locks first leaves the receipt REJECTED, never beyond the ordered quantity (C-29; criterion 10)', function () {
    $purchase = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '10');
    $line = ReceivingWorld::line($purchase);
    $args = [$this->actor, $this->company->id, $purchase->id, $line->id, $this->unit, $this->rack];
    [$actor, $purchaseId, $lineId, $today] = [$this->actor, $purchase->id, $line->id, $this->today];

    $results = Sessions::run([
        'closure' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(app(ClosePurchaseRemainder::class)->run((string) Str::uuid(), $actor, Purchase::query()->findOrFail($purchaseId), [$lineId => '6'], 'Tutup sisa', $today, false)), 'the closure holds its locks'),
        'receipt' => static function () use ($args): string {
            Barrier::await('the closure holds its locks');

            return ReceivingWorld::receive(...$args, usable: '6');
        },
    ]);
    $balance = ReceivingWorld::balance($line->id);

    expect($results['closure'])->toBe('COMMITTED')->and($results['receipt'])->toContain('C-29')
        ->and($balance->normal_received_net_qty_base)->toBe('0.000')->and($balance->closed_qty_base)->toBe('6.000')->and(receivingRaceDifferences())->toBe([]);
});

it('M-36: a cancellation holding its locks first leaves the receipt CONFLICT on the purchase\'s state (ST-02; AX-12; criterion 10)', function () {
    $purchase = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '10');
    $line = ReceivingWorld::line($purchase);
    $args = [$this->actor, $this->company->id, $purchase->id, $line->id, $this->unit, $this->rack];
    [$actor, $purchaseId, $today] = [$this->actor, $purchase->id, $this->today];

    $results = Sessions::run([
        'cancel' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(app(CancelPurchase::class)->run((string) Str::uuid(), $actor, Purchase::query()->findOrFail($purchaseId), 'Batal', $today, false)), 'the cancel holds its locks'),
        'receipt' => static function () use ($args): string {
            Barrier::await('the cancel holds its locks');

            return ReceivingWorld::receive(...$args, usable: '1');
        },
    ]);

    expect($results['cancel'])->toBe('COMMITTED')->and($results['receipt'])->toContain('ST-02')
        ->and(DB::table('stock_movements')->where('movement_type', 'RECEIPT')->count())->toBe(0)->and(receivingRaceDifferences())->toBe([]);
});

it('M-05: two commands receiving one serial leave one success, the serial in stock once (C-02; BR-INV-09; criterion 6)', function () {
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $first = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '5');
    $second = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '5');
    $one = [$this->actor, $this->company->id, $first->id, ReceivingWorld::line($first)->id, $this->unit, $this->rack];
    $other = [$this->actor, $this->company->id, $second->id, ReceivingWorld::line($second)->id, $this->unit, $this->rack];
    $serial = [['serial' => 'SN-RACE', 'damaged' => false]];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => ReceivingWorld::receive(...$one, usable: '1', serials: $serial), 'the first holds its locks'),
        'second' => static function () use ($other, $serial): string {
            Barrier::await('the first holds its locks');

            return ReceivingWorld::receive(...$other, usable: '1', serials: $serial);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-02')
        ->and(DB::table('serial_units')->where('serial_key', 'sn-race')->count())->toBe(1)
        ->and(DB::table('serial_units')->where('serial_key', 'sn-race')->value('state'))->toBe('IN_STOCK')
        ->and(DB::table('stock_movement_entries')->whereNotNull('serial_unit_id')->count())->toBe(1)
        ->and(DB::table('stock_product_balances')->where('product_id', $this->product)->value('on_hand_qty'))->toBe('1.000')
        ->and(ReceivingWorld::serialDifferences())->toBe([])
        ->and(receivingRaceDifferences())->toBe([]);
});

it('M-05: two reversals naming one serial leave one, the other refused under the locks, no unit lost beside it (C-02; AX-05; DATABASE §8; F2)', function () {
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $purchase = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '5');
    $line = ReceivingWorld::line($purchase);
    expect(ReceivingWorld::receive($this->actor, $this->company->id, $purchase->id, $line->id, $this->unit, $this->rack, '2', serials: [['serial' => 'SN-A', 'damaged' => false], ['serial' => 'SN-B', 'damaged' => false]]))->toBe('COMMITTED');
    $lot = ReceivingWorld::lotOf($line->id);
    $serial = (int) DB::table('serial_units')->where('serial_key', 'sn-a')->value('id');
    $args = [$this->actor, $this->company->id, $lot, $this->rack, $serial];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => ReceivingWorld::reverseSerial(...$args), 'the first holds its locks'),
        'second' => static function () use ($args): string {
            Barrier::await('the first holds its locks');

            return ReceivingWorld::reverseSerial(...$args);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-02')
        ->and(DB::table('stock_lot_balances')->where('lot_id', $lot)->sum('qty'))->toEqual(1)
        ->and(DB::table('serial_units')->where('serial_key', 'sn-b')->value('state'))->toBe('IN_STOCK')
        ->and(ReceivingWorld::serialDifferences())->toBe([])
        ->and(receivingRaceDifferences())->toBe([]);
});

it('M-30: concurrent first use of a scope creates one row, both receipts counted in it (FU-01; FU-02)', function () {
    $first = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '5');
    $second = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '5');
    $one = [$this->actor, $this->company->id, $first->id, ReceivingWorld::line($first)->id, $this->unit, $this->rack];
    $other = [$this->actor, $this->company->id, $second->id, ReceivingWorld::line($second)->id, $this->unit, $this->rack];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => ReceivingWorld::receive(...$one, usable: '2'), 'the first holds its locks'),
        'second' => static function () use ($other): string {
            Barrier::await('the first holds its locks');

            return ReceivingWorld::receive(...$other, usable: '3');
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toBe('COMMITTED')
        ->and(DB::table('stock_scope_balances')->where('product_id', $this->product)->where('location_id', $this->rack)->where('condition', 'USABLE')->count())->toBe(1)
        ->and(DB::table('stock_scope_balances')->where('product_id', $this->product)->where('location_id', $this->rack)->where('condition', 'USABLE')->value('claims_qty'))->toBe('5.000')
        ->and(DB::table('stock_product_balances')->where('product_id', $this->product)->value('on_hand_qty'))->toBe('5.000')
        ->and(DB::table('stock_lot_balances')->where('product_id', $this->product)->count())->toBe(2)
        ->and(receivingRaceDifferences())->toBe([]);
});

it('AX-05: reversing a receipt re-spreads its charge share over the consumed lot in one command, the consumed part never reversed (SF-CHARGE; CM-02; C-03; criterion 11)', function () {
    $company = $this->company->id;
    $bill = (int) DB::table('evidence_documents')->where('public_id', PurchaseWorld::bill($company, '5000'))->value('id');
    PurchaseWorld::numbered($this->company);
    $saved = PurchaseWorld::save($this->actor, PurchaseWorld::entry($company, PurchaseWorld::supplier()->id, $this->product, $this->unit, $this->today, [['bill' => $bill, 'classification' => 'ATTRIBUTABLE', 'amount' => '5000', 'basis' => 'LINE_VALUE', 'values' => []]], '10', '1000'));
    expect($saved)->toBe('COMMITTED');
    $purchase = PurchaseWorld::latest($company);
    $line = ReceivingWorld::line($purchase);
    $args = [$this->actor, $company, $purchase->id, $line->id, $this->unit, $this->rack];

    expect(ReceivingWorld::receive(...$args, usable: '6'))->toBe('COMMITTED');
    $first = ReceivingWorld::lotOf($line->id);
    $world = CostWorld::resume(['company' => $company, 'product' => $this->product, 'location' => $this->rack, 'actor' => $this->actor]);
    $consumption = $world->dispatch($first, '2');
    expect(ReceivingWorld::receive(...$args, usable: '4'))->toBe('COMMITTED');
    $second = ReceivingWorld::lotOf($line->id);
    expect(DB::table('lot_cost_balances')->where('lot_id', $first)->value('remaining_cost'))->toBe('6000.00');

    expect(ReceivingWorld::reverse($this->actor, $company, $first, $this->rack, '5'))->toContain('C-03');

    expect(ReceivingWorld::reverse($this->actor, $company, $second, $this->rack, '4'))->toBe('COMMITTED');
    $balance = ReceivingWorld::balance($line->id);
    expect($balance->normal_received_net_qty_base)->toBe('6.000')->and($balance->posted_goods_value)->toBe('6000.00')
        ->and(DB::table('lot_cost_balances')->where('lot_id', $second)->value('remaining_cost'))->toBe('0.00')
        ->and(DB::table('cost_attributions')->where('stock_consumption_id', $consumption)->count())->toBeGreaterThan(1)
        ->and(DB::table('stock_lot_balances')->where('lot_id', $first)->sum('qty'))->toEqual(4)
        ->and(receivingRaceDifferences())->toBe([]);

    expect(ReceivingWorld::reverse($this->actor, $company, $first, $this->rack, '4'))->toBe('COMMITTED')
        ->and(DB::table('stock_lot_balances')->where('lot_id', $first)->sum('qty'))->toEqual(0)
        ->and(receivingRaceDifferences())->toBe([]);
});

it('holds the guard CHECKs against a violating write after a real receipt (C-03; C-04; C-07; C-08; C-09; C-24; C-28)', function () {
    $purchase = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '10');
    $line = ReceivingWorld::line($purchase);
    expect(ReceivingWorld::receive($this->actor, $this->company->id, $purchase->id, $line->id, $this->unit, $this->rack, '4'))->toBe('COMMITTED');
    $lot = ReceivingWorld::lotOf($line->id);
    $receiving = (int) DB::table('receiving_lines')->where('purchase_line_id', $line->id)->value('id');
    $movement = (int) DB::table('stock_movement_entries')->where('lot_id', $lot)->value('stock_movement_id');

    Refusal::assert(Refusal::CHECK, 'stock_lot_balances_c03_not_negative', fn () => DB::table('stock_lot_balances')->where('lot_id', $lot)->update(['qty' => -1]));
    Refusal::assert(Refusal::CHECK, 'stock_product_balances_c04_available', fn () => DB::table('stock_product_balances')->where('product_id', $this->product)->update(['reserved_qty' => 5]));
    Refusal::assert(Refusal::CHECK, 'stock_movement_entries_c07_adds', fn () => StockMovementEntry::factory()->create(['stock_movement_id' => $movement, 'movement_type' => 'RECEIPT', 'lot_id' => $lot, 'product_id' => $this->product, 'location_id' => $this->rack, 'condition' => 'USABLE', 'qty_delta_base' => '-1']));
    Refusal::assert(Refusal::UNIQUE, 'inventory_lots_receiving_line_id_unique', fn () => InventoryLot::factory()->create(['acquisition_kind' => 'RECEIPT', 'import_row_id' => null, 'receiving_line_id' => $receiving, 'source_company_id' => $this->company->id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table inventory_lots', fn () => DB::table('inventory_lots')->where('id', $lot)->update(['source_company_id' => InvoiceWorld::make()->company->id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table receiving_lines', fn () => DB::table('receiving_lines')->where('id', $receiving)->update(['qty_base' => '5']));

    // C-24 and C-28: a receipt creates no consumption, no loss, no allocation (L-03; L-44).
    expect(DB::table('stock_consumptions')->count())->toBe(0)->and(DB::table('inventory_lots')->where('id', $lot)->value('source_company_id'))->toBe($this->company->id)
        ->and(receivingRaceDifferences())->toBe([]);
});

it('FX-04 scenario 1: a price correction between partial receipts ends at the corrected total exactly (DATABASE §6.1; CALC-03)', function () {
    $purchase = ReceivingWorld::purchase($this->company, $this->product, $this->goods['box']->id, '1', '100000');
    $line = ReceivingWorld::line($purchase);
    $args = [$this->actor, $this->company->id, $purchase->id, $line->id, $this->unit, $this->rack];

    expect(ReceivingWorld::receive(...$args, usable: '5'))->toBe('COMMITTED');
    $first = ReceivingWorld::lotOf($line->id);
    expect(DB::table('lot_cost_balances')->where('lot_id', $first)->value('remaining_cost'))->toBe('41667.00');

    CostWorld::resume(['company' => $this->company->id, 'product' => $this->product, 'location' => $this->rack, 'actor' => $this->actor])->dispatch($first, '2');

    $corrected = app(CorrectPurchaseCost::class)->line((string) Str::uuid(), $this->actor, $purchase, $line->id, 'PRICE_CORRECTION', '120000', 'Harga keliru', $this->today, false);
    expect($corrected->kind)->toBe('COMMITTED', json_encode($corrected->state))->and(ReceivingWorld::balance($line->id)->posted_goods_value)->toBe('50000.00')
        ->and(DB::table('lot_cost_balances')->where('lot_id', $first)->value('remaining_cost'))->toBe('30000.00');

    expect(ReceivingWorld::receive(...$args, usable: '7'))->toBe('COMMITTED');
    $second = ReceivingWorld::lotOf($line->id);
    expect(DB::table('lot_cost_balances')->where('lot_id', $second)->value('remaining_cost'))->toBe('70000.00')
        ->and(ReceivingWorld::balance($line->id)->posted_goods_value)->toBe('120000.00')
        ->and(receivingRaceDifferences())->toBe([]);
});

it('FX-04 scenario 10: corrective receipts under one delivery reference (BD-01; C-49; C-59; CM-02; CM-04; criterion 12)', function () {
    $purchase = ReceivingWorld::purchase($this->company, $this->product, $this->unit, '10');
    $line = ReceivingWorld::line($purchase);
    $args = [$this->actor, $this->company->id, $purchase->id, $line->id, $this->unit, $this->rack];

    expect(ReceivingWorld::receive(...$args, usable: '8', reference: 'DN-100'))->toBe('COMMITTED');
    $original = (int) DB::table('receiving_lines')->where('purchase_line_id', $line->id)->value('id');
    expect(ReceivingWorld::receive(...$args, usable: '2', reference: 'DN-100', kind: ReceiptEntry::SUPPLEMENT, cites: $original))->toBe('COMMITTED')
        ->and(ReceivingWorld::receive(...$args, usable: '1', reference: 'DN-100'))->toContain('BD-01')
        ->and(ReceivingWorld::receive(...$args, usable: '1', reference: 'DN-100', kind: ReceiptEntry::RE_RECEIPT, cites: $original))->toContain('C-59');

    $lot = (int) DB::table('inventory_lots')->where('receiving_line_id', $original)->value('id');
    expect(ReceivingWorld::reverse($this->actor, $this->company->id, $lot, $this->rack, '8'))->toBe('COMMITTED')
        ->and(ReceivingWorld::receive(...$args, usable: '8', reference: 'DN-100', kind: ReceiptEntry::RE_RECEIPT, cites: $original))->toBe('COMMITTED')
        ->and(ReceivingWorld::balance($line->id)->normal_received_net_qty_base)->toBe('10.000')
        ->and(receivingRaceDifferences())->toBe([]);
});
