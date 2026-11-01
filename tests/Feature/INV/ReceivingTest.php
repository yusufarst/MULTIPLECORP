<?php

use App\Modules\CAT\Models\Product;
use App\Modules\INV\Queries\ReceivingQueries;
use App\Support\BusinessDate;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use App\Support\RowHandle;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Database\Factories\CAT\ProductBarcodeFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\OPS\CorrectionCaseFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-039): ROUTE-021, ROUTE-022, ROUTE-181, ROUTE-183 and ROUTE-282 with each refusal and each receipt kind; the acceptance criteria named on each.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company);
    $this->goods = Projects::product();
    $this->unit = $this->goods['base']->code;
    $this->rack = ReceivingWorld::rack();
    $this->purchase = ReceivingWorld::purchase($this->company, $this->goods['product']->id, $this->goods['base']->id);
    $this->line = ReceivingWorld::line($this->purchase);
    $this->url = "/gudang/barang-masuk/{$this->purchase->public_id}";
    $this->today = BusinessDate::today();
});

afterEach(fn () => @unlink($this->hotFile));

function receivingAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function receivingDifferences(): array
{
    $found = [];

    foreach (['stock_product_balances', 'stock_lot_balances', 'purchase_line_balances', 'lot_cost_balances', 'purchase_charge_balances', 'purchase_charge_share_balances'] as $family) {
        $named = Families::named($family);

        if ($named !== null) {
            $found = [...$found, ...(new Reconciler)->differences(DB::connection(), $named)];
        }
    }

    return $found;
}

function received(object $test, array $request): array
{
    $test->actingAs($test->actor)->post("{$test->url}/terima", $request)->assertRedirect($test->url);

    return receivingAnswer();
}

it('records two events and two lots for a line of 5 received in batches of 2 and 3, the remainder 3 and then 0 (AC-22; CALC-03; PX-01; criterion 1)', function () {
    $purchase = ReceivingWorld::purchase($this->company, $this->goods['product']->id, $this->goods['base']->id, '5');
    $line = ReceivingWorld::line($purchase);
    $this->url = "/gudang/barang-masuk/{$purchase->public_id}";

    expect(received($this, ReceivingWorld::request($line, $this->unit, $this->rack, '2', more: ['referensi' => 'SJ-1']))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    $balance = ReceivingWorld::balance($line->id);
    expect(bcsubLocal($balance->ordered_qty_base, $balance->normal_received_net_qty_base))->toBe('3');

    expect(received($this, ReceivingWorld::request($line, $this->unit, $this->rack, '3', more: ['referensi' => 'SJ-2']))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    $balance = ReceivingWorld::balance($line->id);
    expect(bcsubLocal($balance->ordered_qty_base, $balance->normal_received_net_qty_base))->toBe('0')
        ->and(DB::table('receiving_lines')->where('purchase_line_id', $line->id)->count())->toBe(2)
        ->and(DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->where('r.purchase_line_id', $line->id)->count())->toBe(2)
        ->and(receivingDifferences())->toBe([]);
});

function bcsubLocal(string $a, string $b): string
{
    return (string) BigDecimal::of($a)->minus($b)->toScale(0, RoundingMode::Down);
}

it('puts 55 usable at the rack and 5 straight into UNUSABLE on one lot, AVAILABLE up by 55, 40 open (PX-06; L-13; BR-INV-01; criterion 2)', function () {
    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '55', '5'))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    $product = DB::table('stock_product_balances')->where('product_id', $this->line->product_id)->first();
    $lot = DB::table('inventory_lots')->where('product_id', $this->line->product_id)->sole();
    $places = DB::table('stock_lot_balances')->where('lot_id', $lot->id)->orderBy('condition')->get();

    expect($product->on_hand_qty)->toBe('60.000')->and($product->unusable_qty)->toBe('5.000')
        ->and($places->pluck('condition')->all())->toBe(['UNUSABLE', 'USABLE'])->and($places[0]->qty)->toBe('5.000')->and($places[1]->qty)->toBe('55.000')
        ->and($places[1]->location_id)->toBe($this->rack)
        ->and(bcsubLocal(ReceivingWorld::balance($this->line->id)->ordered_qty_base, ReceivingWorld::balance($this->line->id)->normal_received_net_qty_base))->toBe('40')
        ->and(receivingDifferences())->toBe([]);
});

it('records units rejected at the door on the line with no stock event, the remainder open (L-13; criterion 3)', function () {
    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '0', '0', '7'))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));

    expect(DB::table('receiving_lines')->where('purchase_line_id', $this->line->id)->value('rejected_qty_base'))->toBe('7.000')
        ->and(DB::table('stock_movement_entries')->count())->toBe(0)->and(DB::table('inventory_lots')->count())->toBe(0)
        ->and(ReceivingWorld::balance($this->line->id)->normal_received_net_qty_base)->toBe('0.000');
});

it('refuses a receipt above the remainder with it in words, a replacement only against its own cap (PX-03; C-29; criterion 4)', function () {
    $answer = received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '101'));
    expect($answer['code'])->toBe('C-29')->and($answer['text'])->toContain('100')->and(DB::table('inventory_lots')->count())->toBe(0);

    $case = CorrectionCaseFactory::new()->createOne(['company_id' => $this->company->id, 'case_kind' => 'PURCHASE_RETURN']);
    $answer = received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1', more: ['jenis' => 'REPLACEMENT', 'kasus' => $case->public_id]));
    expect($answer['code'])->toBe('C-29')->and(ReceivingWorld::balance($this->line->id)->normal_received_net_qty_base)->toBe('0.000');
});

it('creates one lot of the purchase\'s company, keyed to its line, at the line\'s actual cost, with an opaque code (BR-INV-04; C-08; PJ-23; criterion 5)', function () {
    received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '10'));
    $lot = DB::table('inventory_lots')->sole();
    $receiving = DB::table('receiving_lines')->sole();

    expect($lot->source_company_id)->toBe($this->company->id)->and($lot->receiving_line_id)->toBe($receiving->id)->and($lot->lot_code)->toMatch('/^LOT-[A-Z0-9]{12}$/')
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot->id)->value('remaining_cost'))->toBe('10000.00')
        ->and(ReceivingWorld::balance($this->line->id)->posted_goods_value)->toBe('10000.00');
});

it('registers serials, counts a double scan once, refuses one in stock and the wrong count (BR-INV-09; C-02; BD-18; criterion 6)', function () {
    Product::query()->whereKey($this->line->product_id)->toBase()->update(['is_serialized' => true]);
    $serials = [['nomor' => 'SN-1'], ['nomor' => 'sn-1'], ['nomor' => 'SN-2', 'rusak' => true]];

    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1', '1', more: ['seri' => $serials]))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    expect(DB::table('serial_units')->where('state', 'IN_STOCK')->count())->toBe(2)->and(DB::table('serial_units')->where('serial_key', 'sn-2')->value('current_condition'))->toBe('UNUSABLE')
        ->and(DB::table('stock_movement_entries')->whereNotNull('serial_unit_id')->count())->toBe(2);

    $again = received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1', more: ['seri' => [['nomor' => 'SN-1']]]));
    expect($again['code'])->toBe('C-02')->and($again['text'])->toContain('SN-1');

    $short = received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '2', more: ['seri' => [['nomor' => 'SN-9']]]));
    expect($short['code'])->toBe('C-02');
});

it('answers a scan: a product by barcode or SKU, an unknown code with its offer only with products.edit, an ambiguous one choosing none (SF-SCAN; IP-15; MSG-25; MSG-37; C-01; criterion 7)', function () {
    $product = $this->goods['product'];
    $this->actingAs($this->actor)->postJson('/gudang/pindai', ['kode' => $product->sku])->assertOk()->assertJsonPath('kind', 'match')->assertJsonPath('matches.0.product', $product->public_id);
    $this->actingAs($this->actor)->postJson('/gudang/pindai', ['kode' => 'TIDAK-ADA'])->assertOk()->assertJsonPath('kind', 'unknown')->assertJsonPath('register', false);
    expect(DB::table('product_barcodes')->count())->toBe(0);

    $editor = ReceivingWorld::actor($this->company, ['products.edit']);
    $this->actingAs($editor)->postJson('/gudang/pindai', ['kode' => 'TIDAK-ADA'])->assertJsonPath('register', true);

    ProductBarcodeFactory::new()->createOne(['product_id' => $product->id, 'code' => 'KODE-1']);
    $other = Projects::product();
    DB::table('products')->where('id', $other['product']->id)->update(['sku' => 'KODE-1']);
    $this->actingAs($this->actor)->postJson('/gudang/pindai', ['kode' => 'kode-1'])->assertJsonPath('kind', 'ambiguous')->assertJsonCount(2, 'matches');
});

it('answers a second ORIGINAL of one delivery CONFLICT; a SUPPLEMENT cites the receipt it completes (BD-01; C-49; CM-04; criterion 8)', function () {
    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '2', more: ['referensi' => 'SJ-9']))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    $duplicate = received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1', more: ['referensi' => 'SJ-9']));
    expect($duplicate['kind'])->toBe('CONFLICT')->and($duplicate['code'])->toBe('BD-01');

    $receipt = DB::table('receiving_lines')->value('id');
    $supplement = received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1', more: ['referensi' => 'SJ-9', 'jenis' => 'SUPPLEMENT', 'mengoreksi' => RowHandle::of('receiving_lines', (int) $receipt)]));
    expect($supplement['kind'])->toBe('COMMITTED', json_encode($supplement))->and(DB::table('receiving_lines')->where('receipt_kind', 'SUPPLEMENT')->value('corrects_receiving_line_id'))->toBe((int) $receipt);
});

it('commits the receipt and its reservation as one command, at most the usable units, absent on a project no longer running (AX-04; D-UX-12; BR-RSV-03; criterion 9)', function () {
    $project = $this->world->project(['WAREHOUSE'], '20');
    $demand = DB::table('project_lines')->where('project_id', $project->id)->first();
    $unit = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('pu.product_id', $demand->product_id)->where('pu.is_base', true)->first(['u.id', 'u.code']);
    $purchase = ReceivingWorld::purchase($this->company, $demand->product_id, $unit->id, '20', '500', $project->id, $demand->id);
    $line = ReceivingWorld::line($purchase);
    $this->url = "/gudang/barang-masuk/{$purchase->public_id}";

    $above = received($this, ReceivingWorld::request($line, $unit->code, $this->rack, '5', more: ['reservasi' => '6']));
    expect($above['code'])->toBe('BR-RSV-03')->and(DB::table('inventory_lots')->count())->toBe(0);

    $this->actingAs($this->actor)->get($this->url)->assertInertia(fn ($page) => $page->component('INV/ReceiveGoods', false)->where('lines.0.reservable', fn ($value) => $value !== null)->etc());
    $done = received($this, ReceivingWorld::request($line, $unit->code, $this->rack, '5', more: ['reservasi' => '4']));
    expect($done['kind'])->toBe('COMMITTED', json_encode($done))->and(DB::table('reservations')->where('project_line_id', $demand->id)->value('remaining_qty_base'))->toBe('4.000')
        ->and(DB::table('stock_product_balances')->where('product_id', $demand->product_id)->value('reserved_qty'))->toBe('4.000')->and(receivingDifferences())->toBe([]);

    $without = Authorization::admin([$this->company->id], ['stock.view', 'stock.receive']);
    $this->actingAs($without)->get($this->url)->assertInertia(fn ($page) => $page->where('lines.0.reservable', null)->where('can.reserve', false)->etc());
    $this->actingAs($without)->post("{$this->url}/terima", ReceivingWorld::request($line, $unit->code, $this->rack, '1', more: ['reservasi' => '1']));
    expect(receivingAnswer()['code'])->toBe('AZ-01');
});

it('reverses only the unconsumed units, its cost and serials as never received; a consumed lot refused; a RE_RECEIPT cites it (AX-05; CM-02; CM-03; C-59; criteria 11, 12)', function () {
    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '10', more: ['referensi' => 'SJ-1']))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    $receipt = (int) DB::table('receiving_lines')->value('id');
    $handle = RowHandle::of('receiving_lines', $receipt);
    $rack = RowHandle::of('locations', $this->rack);
    $body = fn (string $qty) => ['command_id' => (string) Str::uuid(), 'tempat' => [['rak' => $rack, 'kondisi' => 'USABLE', 'jumlah' => $qty]], 'alasan' => 'Produk salah', 'tanggal' => $this->today];

    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", $body('11'));
    expect(receivingAnswer()['code'])->toBe('C-03');

    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", $body('4'))->assertRedirect("/pembelian/{$this->purchase->public_id}");
    expect(receivingAnswer()['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    $balance = ReceivingWorld::balance($this->line->id);
    expect($balance->normal_received_net_qty_base)->toBe('6.000')->and($balance->posted_goods_value)->toBe('6000.00')
        ->and(DB::table('stock_movements')->where('movement_type', 'RECEIPT_REVERSAL')->value('reason'))->toBe('Produk salah')
        ->and(DB::table('lot_cost_balances')->value('remaining_cost'))->toBe('6000.00')->and(receivingDifferences())->toBe([]);

    $corrected = received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '4', more: ['jenis' => 'RE_RECEIPT', 'mengoreksi' => $handle]));
    expect($corrected['kind'])->toBe('COMMITTED', json_encode($corrected))->and(DB::table('inventory_lots')->count())->toBe(2);
});

it('needs the actor\'s cut where a reversal would leave usable supply below the reservations (IP-20; C-06; criterion 11)', function () {
    $project = $this->world->project(['WAREHOUSE'], '20');
    $demand = DB::table('project_lines')->where('project_id', $project->id)->first();
    $unit = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('pu.product_id', $demand->product_id)->where('pu.is_base', true)->first(['u.id', 'u.code']);
    $purchase = ReceivingWorld::purchase($this->company, $demand->product_id, $unit->id, '20', '500', $project->id, $demand->id);
    $line = ReceivingWorld::line($purchase);
    $this->url = "/gudang/barang-masuk/{$purchase->public_id}";
    received($this, ReceivingWorld::request($line, $unit->code, $this->rack, '5', more: ['reservasi' => '5']));
    $handle = RowHandle::of('receiving_lines', (int) DB::table('receiving_lines')->value('id'));
    $reservation = RowHandle::of('reservations', (int) DB::table('reservations')->value('id'));
    $body = ['tempat' => [['rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '3']], 'alasan' => 'Salah barang', 'tanggal' => $this->today];

    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", [...$body, 'command_id' => (string) Str::uuid()]);
    expect(receivingAnswer()['code'])->toBe('C-06');

    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", [...$body, 'command_id' => (string) Str::uuid(), 'potong' => [['reservasi' => $reservation, 'jumlah' => '3']]]);
    expect(receivingAnswer()['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()))->and(DB::table('reservations')->value('remaining_qty_base'))->toBe('2.000')->and(receivingDifferences())->toBe([]);
});

it('keeps the receiving projection free of any price, tax, charge or cost (PJ-18; SM-25; criterion 13)', function () {
    $receiver = Authorization::admin([$this->company->id], ['stock.view', 'stock.receive']);
    $this->actingAs($receiver)->post("{$this->url}/terima", ReceivingWorld::request($this->line, $this->unit, $this->rack, '10'));
    expect(receivingAnswer()['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));

    $page = $this->actingAs($receiver)->get($this->url)->assertOk()->getContent();
    expect($page)->not->toContain('unit_price')->not->toContain('line_value')->not->toContain('goods_value')->not->toContain('remaining_cost');
    $this->actingAs($receiver)->get('/gudang/barang-masuk')->assertOk();
});

it('answers a changed serial policy or scale CONFLICT with the current policy (ST-09; L-35; criterion 16)', function () {
    $answer = received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1', more: ['kebijakan_seri' => true]));
    expect($answer['kind'])->toBe('CONFLICT')->and($answer['code'])->toBe('ST-09')->and(DB::table('receiving_lines')->count())->toBe(0);
});

it('refuses a drop-ship line, and a purchase no longer open (BR-INV-11; WF-INV-01)', function () {
    DB::table('purchase_lines')->where('id', $this->line->id)->update(['delivery_mode' => 'DROPSHIP']);
    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1'))['code'])->toBe('BR-INV-11');
    DB::table('purchase_lines')->where('id', $this->line->id)->update(['delivery_mode' => 'WAREHOUSE']);

    DB::table('purchases')->where('id', $this->purchase->id)->update(['state' => 'CANCELLED', 'cancelled_by' => $this->actor->id, 'cancelled_at' => now(), 'cancelled_reason' => 'Batal']);
    $answer = received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1'));
    expect($answer['kind'])->toBe('CONFLICT')->and($answer['code'])->toBe('ST-02');
});

it('lists open warehouse remainders per company on PG-021, none of another company (QS-05; CS-07; PJ-18)', function () {
    $response = $this->actingAs($this->actor)->get('/gudang/barang-masuk');
    $response->assertInertia(fn ($page) => $page->component('INV/ReceivingWorklist', false)->has('groups', 1)->where('groups.0.code', $this->company->code)->etc());
    $rows = app(ReceivingQueries::class)->worklist($this->actor->id, $this->company->id, null);

    expect($rows['rows'][0]['number'])->toBe($this->purchase->purchase_number)->and($rows['rows'][0]['remaining'])->toBe('100.000')->and($rows['rows'][0])->not->toHaveKey('value');

    $other = InvoiceWorld::make();
    $stranger = ReceivingWorld::actor($other->company);
    $this->actingAs($stranger)->get($this->url)->assertNotFound();
    $this->actingAs($stranger)->post("{$this->url}/terima", ReceivingWorld::request($this->line, $this->unit, $this->rack, '1'))->assertNotFound();
});

it('links a receipt\'s evidence to the movement it proves (IP-16; SF-EVIDENCE; criterion 1)', function () {
    $proof = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->company->id, 'evidence_type' => 'DELIVERY_PROOF']);

    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '2', more: ['bukti' => [$proof->public_id]]))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    $link = DB::table('evidence_links')->where('evidence_document_id', $proof->id)->sole();
    expect($link->stock_movement_id)->toBe(DB::table('stock_movements')->where('movement_type', 'RECEIPT')->value('id'))->and($link->role)->toBe('PRIMARY_PROOF');
});

it('reverses a serial only as itself: this lot\'s, at its place and condition, then NEVER_RECEIVED and receivable again (C-02; BR-INV-09; DATABASE §8; AX-05; criterion 11)', function () {
    Product::query()->whereKey($this->line->product_id)->toBase()->update(['is_serialized' => true]);
    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1', '1', more: ['seri' => [['nomor' => 'SN-1'], ['nomor' => 'SN-2', 'rusak' => true]]]))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    expect(ReceivingWorld::serialDifferences())->toBe([]);
    $receipt = DB::table('receiving_lines')->value('id');
    $handle = RowHandle::of('receiving_lines', (int) $receipt);
    $rack = RowHandle::of('locations', $this->rack);
    $first = (int) DB::table('serial_units')->where('serial_key', 'sn-1')->value('id');
    $reverse = fn (array $place, ?string $command = null) => $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", ['command_id' => $command ?? (string) Str::uuid(), 'tempat' => [$place], 'alasan' => 'Seri salah', 'tanggal' => $this->today]);

    // The wrong condition, and no serial at all: the guards and the serial rows would disagree.
    $reverse(['rak' => $rack, 'kondisi' => 'UNUSABLE', 'jumlah' => '1', 'seri' => RowHandle::of('serial_units', $first)]);
    expect(receivingAnswer()['code'])->toBe('C-02');
    $reverse(['rak' => $rack, 'kondisi' => 'USABLE', 'jumlah' => '1']);
    expect(receivingAnswer()['code'])->toBe('C-02')->and(DB::table('stock_movements')->where('movement_type', 'RECEIPT_REVERSAL')->count())->toBe(0);

    $command = (string) Str::uuid();
    $body = ['rak' => $rack, 'kondisi' => 'USABLE', 'jumlah' => '1', 'seri' => RowHandle::of('serial_units', $first)];
    $reverse($body, $command);
    expect(receivingAnswer()['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()))->and(DB::table('serial_units')->where('id', $first)->value('state'))->toBe('NEVER_RECEIVED')
        ->and(DB::table('serial_units')->where('serial_key', 'sn-2')->value('state'))->toBe('IN_STOCK')->and(ReceivingWorld::serialDifferences())->toBe([])->and(receivingDifferences())->toBe([]);

    // The lost answer's retry finds its record, though the serial is no longer in stock (CI-05).
    $reverse($body, $command)->assertRedirect();
    expect(receivingAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('stock_movements')->where('movement_type', 'RECEIPT_REVERSAL')->count())->toBe(1);

    // Twice, now as a stale form: its place holds nothing more.
    $reverse($body);
    expect(receivingAnswer()['code'])->toBe('C-03');

    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1', more: ['jenis' => 'RE_RECEIPT', 'mengoreksi' => $handle, 'seri' => [['nomor' => 'SN-1']]]))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    expect(DB::table('serial_units')->where('id', $first)->value('state'))->toBe('IN_STOCK')->and(ReceivingWorld::serialDifferences())->toBe([]);
});

it('costs a replacement from its case over the units still expected, moving the case\'s replaced value both ways, the normal remainder untouched (C-29; C-57; C-59; PX-03; FX-04 scenario 5; criterion 4)', function () {
    $case = CorrectionCaseFactory::new()->createOne(['company_id' => $this->company->id, 'case_kind' => 'PURCHASE_RETURN', 'returned_cost' => '1000.00']);
    DB::table('purchase_line_balances')->where('purchase_line_id', $this->line->id)->update(['replacement_expected_qty_base' => '10']);
    $replacement = fn (string $qty): array => received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, $qty, more: ['jenis' => 'REPLACEMENT', 'kasus' => $case->public_id]));
    $replaced = fn (): string => (string) DB::table('correction_cases')->where('id', $case->id)->value('replaced_value');
    $cost = fn (): string => (string) DB::table('lot_cost_balances')->where('lot_id', DB::table('inventory_lots')->max('id'))->value('remaining_cost');

    $this->actingAs($this->actor)->get($this->url)->assertInertia(fn ($page) => $page->where('lines.0.replacement', '10.000')->where('cases.0.id', $case->public_id)->etc());

    expect($replacement('4')['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()))->and($cost())->toBe('400.00')->and($replaced())->toBe('400.00');
    expect($replacement('6')['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()))->and($cost())->toBe('600.00')->and($replaced())->toBe('1000.00');
    expect($replacement('1')['code'])->toBe('C-29');
    $balance = ReceivingWorld::balance($this->line->id);
    expect($balance->replacement_received_net_qty_base)->toBe('10.000')->and($balance->normal_received_net_qty_base)->toBe('0.000')->and($balance->posted_goods_value)->toBe('0.00');

    $handle = RowHandle::of('receiving_lines', (int) DB::table('receiving_lines')->max('id'));
    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", ['command_id' => (string) Str::uuid(), 'tempat' => [['rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '6']], 'alasan' => 'Pengganti salah', 'tanggal' => $this->today]);
    expect(receivingAnswer()['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()))->and($replaced())->toBe('400.00')
        ->and(ReceivingWorld::balance($this->line->id)->replacement_received_net_qty_base)->toBe('4.000');
});

it('refuses a replacement citing a closed case, a case of another kind, or a purchase no longer open (C-59; AX-04)', function () {
    DB::table('purchase_line_balances')->where('purchase_line_id', $this->line->id)->update(['replacement_expected_qty_base' => '10']);
    $other = CorrectionCaseFactory::new()->createOne(['company_id' => $this->company->id, 'case_kind' => 'OTHER']);

    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1', more: ['jenis' => 'REPLACEMENT', 'kasus' => $other->public_id]))['code'])->toBe('C-59');

    $case = CorrectionCaseFactory::new()->createOne(['company_id' => $this->company->id, 'case_kind' => 'PURCHASE_RETURN']);
    DB::table('purchases')->where('id', $this->purchase->id)->update(['state' => 'CANCELLED', 'cancelled_by' => $this->actor->id, 'cancelled_at' => now(), 'cancelled_reason' => 'Batal']);
    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '1', more: ['jenis' => 'REPLACEMENT', 'kasus' => $case->public_id]))['code'])->toBe('ST-02');
});

it('refuses a rack no longer active, though a replay still finds its answer (C-14; CI-05)', function () {
    $body = ReceivingWorld::request($this->line, $this->unit, $this->rack, '2');
    expect(received($this, $body)['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    DB::table('locations')->where('id', $this->rack)->update(['is_active' => false]);

    expect(received($this, $body)['kind'])->toBe('COMMITTED')->and(DB::table('receiving_lines')->count())->toBe(1);
    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '2'))['code'])->toBe('C-14');
});

it('holds the company filter and the closed groups in the address (NAV-021)', function () {
    $other = InvoiceWorld::make()->company;
    $both = ReceivingWorld::actor($this->company);
    $grant = Authorization::admin([$this->company->id, $other->id], ReceivingWorld::CAPABILITIES);
    unset($both);

    $this->actingAs($grant)->get('/gudang/barang-masuk')->assertInertia(fn ($page) => $page->component('INV/ReceivingWorklist', false)->has('groups', 2)->has('companies', 2)->where('chosen', null)->etc());
    $this->actingAs($grant)->get("/gudang/barang-masuk?perusahaan={$other->public_id}")->assertInertia(fn ($page) => $page->has('groups', 1)->has('companies', 2)->where('groups.0.id', $other->public_id)->where('chosen', $other->public_id)->etc());
    $this->actingAs($grant)->get("/gudang/barang-masuk?tutup={$other->public_id}")->assertInertia(fn ($page) => $page->has('groups', 2)->where('closed', [$other->public_id])->etc());
});

it('reverses a lot by its tracked units after the product\'s serial policy was switched off (C-02; L-35)', function () {
    Product::query()->whereKey($this->line->product_id)->toBase()->update(['is_serialized' => true]);
    expect(received($this, ReceivingWorld::request($this->line, $this->unit, $this->rack, '2', more: ['seri' => [['nomor' => 'SN-K1'], ['nomor' => 'SN-K2']]]))['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()));
    Product::query()->whereKey($this->line->product_id)->toBase()->update(['is_serialized' => false]);
    $handle = RowHandle::of('receiving_lines', (int) DB::table('receiving_lines')->value('id'));
    $rack = RowHandle::of('locations', $this->rack);
    $serial = (int) DB::table('serial_units')->where('serial_key', 'sn-k1')->value('id');
    $reverse = fn (array $place) => $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", ['command_id' => (string) Str::uuid(), 'tempat' => [$place], 'alasan' => 'Seri salah', 'tanggal' => $this->today]);

    $reverse(['rak' => $rack, 'kondisi' => 'USABLE', 'jumlah' => '1']);
    expect(receivingAnswer()['code'])->toBe('C-02')->and(DB::table('stock_lot_balances')->value('qty'))->toBe('2.000');

    $reverse(['rak' => $rack, 'kondisi' => 'USABLE', 'jumlah' => '1', 'seri' => RowHandle::of('serial_units', $serial)]);
    expect(receivingAnswer()['kind'])->toBe('COMMITTED', json_encode(receivingAnswer()))->and(DB::table('serial_units')->where('id', $serial)->value('state'))->toBe('NEVER_RECEIVED')
        ->and(DB::table('stock_lot_balances')->value('qty'))->toBe('1.000');
});

it('registers an unknown code from the scan inline, on PG-022, and the scan then finds it (ROUTE-147; INT-147; IP-15)', function () {
    $code = 'KODE-'.Str::upper(Str::random(6));
    $editor = ReceivingWorld::actor($this->company, ['products.edit']);

    expect($this->actingAs($editor)->post('/gudang/pindai', ['kode' => $code])->json('kind'))->toBe('unknown');
    $this->actingAs($editor)->from($this->url)->post("/data-master/produk/{$this->goods['product']->public_id}/barcode", ['command_id' => (string) Str::uuid(), 'kind' => 'MANUFACTURER', 'code' => $code, 'inline' => true])->assertRedirect($this->url);

    $found = $this->actingAs($editor)->post('/gudang/pindai', ['kode' => $code])->json();
    expect($found['kind'])->toBe('match')->and($found['matches'][0]['product'])->toBe($this->goods['product']->public_id);
});
