<?php

use App\Modules\INV\Queries\ReceivingQueries;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;

// TL-06, TL-14 (TASK-039; W4-13; W4-18; AZ-01; AZ-11; SM-01; SM-04; SM-25; PJ-18; PJ-23; IG-01; CS-04): `stock.receive` with its `stock.view` prerequisite, `stock.reserve` for the offer and the cut, `stock.reverse` as ADM_PLUS, `documents.issue` for DOC-12, each control absent without its capability; a foreign purchase, line, rack, serial or evidence answered like none; the receiving projection without `cost.view`.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-12');
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
    $this->request = fn (array $more = []): array => ReceivingWorld::request($this->line, $this->unit, $this->rack, '2', more: $more);
    $this->other = InvoiceWorld::make()->company;
});

afterEach(fn () => @unlink($this->hotFile));

function receiptAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function receiptHandle(object $test): string
{
    $test->actingAs($test->actor)->post("{$test->url}/terima", ($test->request)());
    expect(receiptAnswer()['kind'])->toBe('COMMITTED', json_encode(receiptAnswer()));

    return RowHandle::of('receiving_lines', (int) DB::table('receiving_lines')->value('id'));
}

it('denies stock.receive without its stock.view prerequisite (AZ-01; W4-13)', function () {
    $without = Authorization::admin([$this->company->id], ['stock.receive']);

    $this->actingAs($without)->get('/gudang/barang-masuk')->assertForbidden();
    $this->actingAs($without)->get($this->url)->assertForbidden();
    $this->actingAs($without)->post("{$this->url}/terima", ($this->request)())->assertForbidden();
    expect(DB::table('receiving_lines')->count())->toBe(0);
});

it('forbids ROUTE-021, ROUTE-022 and ROUTE-181 without stock.receive, the entry on PG-016 absent (SM-04; IG-01)', function () {
    $viewer = Authorization::admin([$this->company->id], ['stock.view', 'cost.view', 'purchase.manage']);

    $this->actingAs($viewer)->get('/gudang/barang-masuk')->assertForbidden();
    $this->actingAs($viewer)->get($this->url)->assertForbidden();
    $this->actingAs($viewer)->post("{$this->url}/terima", ($this->request)())->assertForbidden();
    $this->actingAs($viewer)->get("/pembelian/{$this->purchase->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.receive', false)->where('can.reverse', false)->etc());
});

it('forbids ROUTE-183 without stock.reverse, the control absent and the reversal figures unread (W4-18; SM-04; IG-01)', function () {
    $handle = receiptHandle($this);
    $viewer = Authorization::admin([$this->company->id], ['stock.view', 'stock.receive', 'cost.view', 'purchase.manage']);
    $body = ['command_id' => (string) Str::uuid(), 'tempat' => [['rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '1']], 'alasan' => 'Salah barang', 'tanggal' => $this->today];

    $this->actingAs($viewer)->post("/gudang/penerimaan/{$handle}/balikkan", $body)->assertForbidden();
    $this->actingAs($viewer)->get("/pembelian/{$this->purchase->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.reverse', false)->where('receipts.0.reversal', null)->etc());
    expect(DB::table('stock_movements')->where('movement_type', 'RECEIPT_REVERSAL')->count())->toBe(0);
});

it('needs stock.reserve for the offer and for a reversal\'s cut (D-UX-12; IP-20; W4-18)', function () {
    $project = $this->world->project(['WAREHOUSE'], '20');
    $demand = DB::table('project_lines')->where('project_id', $project->id)->first();
    $unit = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('pu.product_id', $demand->product_id)->where('pu.is_base', true)->first(['u.id', 'u.code']);
    $purchase = ReceivingWorld::purchase($this->company, $demand->product_id, $unit->id, '20', '500', $project->id, $demand->id);
    $line = ReceivingWorld::line($purchase);
    $url = "/gudang/barang-masuk/{$purchase->public_id}";
    $without = Authorization::admin([$this->company->id], ['stock.view', 'stock.receive', 'stock.reverse']);

    $this->actingAs($without)->post("{$url}/terima", ReceivingWorld::request($line, $unit->code, $this->rack, '5', more: ['reservasi' => '5']));
    expect(receiptAnswer()['code'])->toBe('AZ-01')->and(DB::table('receiving_lines')->count())->toBe(0);

    $this->actingAs($this->actor)->post("{$url}/terima", ReceivingWorld::request($line, $unit->code, $this->rack, '5', more: ['reservasi' => '5']));
    expect(receiptAnswer()['kind'])->toBe('COMMITTED', json_encode(receiptAnswer()));
    $handle = RowHandle::of('receiving_lines', (int) DB::table('receiving_lines')->value('id'));
    $reservation = RowHandle::of('reservations', (int) DB::table('reservations')->value('id'));

    $this->actingAs($without)->post("/gudang/penerimaan/{$handle}/balikkan", ['command_id' => (string) Str::uuid(), 'tempat' => [['rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '3']], 'potong' => [['reservasi' => $reservation, 'jumlah' => '3']], 'alasan' => 'Salah barang', 'tanggal' => $this->today]);
    expect(receiptAnswer()['code'])->toBe('AZ-08')->and(DB::table('audit_events')->where('action', 'INV.ReservationCutRouted')->count())->toBe(1)->and(DB::table('reservations')->value('remaining_qty_base'))->toBe('5.000')
        ->and(DB::table('stock_movements')->where('movement_type', 'RECEIPT_REVERSAL')->count())->toBe(0);
});

it('needs documents.issue for DOC-12, the control absent without it (W4-13; SM-04; IG-01)', function () {
    receiptHandle($this);
    $movement = DB::table('stock_movements')->where('movement_type', 'RECEIPT')->value('public_id');
    $without = Authorization::admin([$this->company->id], ['stock.view', 'stock.receive']);

    $this->actingAs($without)->get($this->url)->assertInertia(fn (AssertableInertia $page) => $page->where('can.note', false)->etc());
    $this->actingAs($without)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-12', 'subjek' => ['receiving_movement_id' => $movement]])->assertForbidden();
    expect(DB::table('documents')->count())->toBe(0);
});

it('answers a purchase of another company like none, reading and writing (SM-01; AZ-11; CS-04)', function () {
    $foreign = ReceivingWorld::purchase($this->other, $this->goods['product']->id, $this->goods['base']->id);
    $url = "/gudang/barang-masuk/{$foreign->public_id}";

    $this->actingAs($this->actor)->get($url)->assertNotFound();
    $this->actingAs($this->actor)->post("{$url}/terima", ReceivingWorld::request(ReceivingWorld::line($foreign), $this->unit, $this->rack, '1'))->assertNotFound();
    expect(DB::table('receiving_lines')->count())->toBe(0);
});

it('answers a foreign line, evidence and cited receipt at their fields, a foreign receipt like none (SM-01; CS-04)', function () {
    $foreign = ReceivingWorld::purchase($this->other, $this->goods['product']->id, $this->goods['base']->id);
    $foreignLine = ReceivingWorld::line($foreign);
    $foreignActor = ReceivingWorld::actor($this->other);
    $this->actingAs($foreignActor)->post("/gudang/barang-masuk/{$foreign->public_id}/terima", ReceivingWorld::request($foreignLine, $this->unit, $this->rack, '1'));
    expect(receiptAnswer()['kind'])->toBe('COMMITTED', json_encode(receiptAnswer()));
    $foreignReceipt = RowHandle::of('receiving_lines', (int) DB::table('receiving_lines')->value('id'));
    $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $this->other->id]);

    $this->actingAs($this->actor)->post("{$this->url}/terima", [...($this->request)(), 'baris' => RowHandle::of('purchase_lines', $foreignLine->id)])->assertSessionHasErrors('baris');
    $this->actingAs($this->actor)->post("{$this->url}/terima", ($this->request)(['bukti' => [$evidence->public_id]]))->assertSessionHasErrors('bukti.0');
    $this->actingAs($this->actor)->post("{$this->url}/terima", ($this->request)(['jenis' => 'SUPPLEMENT', 'mengoreksi' => $foreignReceipt]))->assertSessionHasErrors('mengoreksi');
    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$foreignReceipt}/balikkan", ['command_id' => (string) Str::uuid(), 'tempat' => [['rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '1']], 'alasan' => 'Salah barang', 'tanggal' => $this->today])->assertNotFound();
    expect(DB::table('receiving_lines')->count())->toBe(1)->and(DB::table('stock_movements')->where('movement_type', 'RECEIPT_REVERSAL')->count())->toBe(0);
});

it('takes no company, source or cost from the payload (CS-04; DP-03; WS-02; C-08)', function () {
    $this->actingAs($this->actor)->post("{$this->url}/terima", ($this->request)(['perusahaan' => $this->other->public_id, 'biaya' => '1', 'sumber' => $this->other->id]));
    expect(receiptAnswer()['kind'])->toBe('COMMITTED', json_encode(receiptAnswer()));
    $lot = DB::table('inventory_lots')->sole();

    expect($lot->source_company_id)->toBe($this->company->id)->and(DB::table('lot_cost_balances')->where('lot_id', $lot->id)->value('remaining_cost'))->toBe('2000.00');
});

it('never shows a price, tax, charge or cost to a receiver without cost.view on PG-021, PG-022 or the scan (SM-25; PJ-18; criterion 13)', function () {
    $receiver = Authorization::admin([$this->company->id], ['stock.view', 'stock.receive', 'documents.issue']);
    $this->actingAs($receiver)->post("{$this->url}/terima", ($this->request)());
    expect(receiptAnswer()['kind'])->toBe('COMMITTED', json_encode(receiptAnswer()));
    $forbidden = '/"(unit_price|price|harga|line_value|tax|pajak|charge|cost|actual_cost|amount|total|remaining_cost)"/i';

    $screen = $this->actingAs($receiver)->get($this->url)->assertOk()->viewData('page');
    expect(json_encode($screen['props']))->not->toMatch($forbidden);

    expect(json_encode(app(ReceivingQueries::class)->worklist($receiver->id, $this->company->id, null)))->not->toMatch($forbidden);

    $scan = $this->actingAs($receiver)->post('/gudang/pindai', ['kode' => $this->goods['product']->sku])->assertOk();
    expect($scan->getContent())->not->toMatch($forbidden);
});

it('keeps a new lot\'s code opaque and the receipt\'s scan to stock.view (PJ-23; SM-04)', function () {
    $without = Authorization::admin([$this->company->id], ['projects.view']);
    $this->actingAs($without)->post('/gudang/pindai', ['kode' => 'APA-SAJA'])->assertForbidden();

    receiptHandle($this);
    expect(DB::table('inventory_lots')->value('lot_code'))->toMatch('/^LOT-[A-Z0-9]{12}$/');
});

it('names another company\'s reservation on PG-016 by its relation marker only, offers no cut without stock.reserve, and hides the reference and the lot code without their capabilities (IP-20; PJ-01; PJ-03; PJ-04; CS-07; IG-01)', function () {
    $foreign = InvoiceWorld::make();
    $foreignActor = ReceivingWorld::actor($foreign->company);
    $project = $foreign->project(['WAREHOUSE'], '20');
    $demand = DB::table('project_lines')->where('project_id', $project->id)->first();
    $unit = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('pu.product_id', $demand->product_id)->where('pu.is_base', true)->first(['u.id', 'u.code']);
    $theirs = ReceivingWorld::purchase($foreign->company, $demand->product_id, $unit->id, '20', '500', $project->id, $demand->id);
    $this->actingAs($foreignActor)->post("/gudang/barang-masuk/{$theirs->public_id}/terima", ReceivingWorld::request(ReceivingWorld::line($theirs), $unit->code, $this->rack, '5', more: ['reservasi' => '5']));
    expect(receiptAnswer()['kind'])->toBe('COMMITTED', json_encode(receiptAnswer()));

    $ours = ReceivingWorld::purchase($this->company, $demand->product_id, $unit->id);
    $this->actingAs($this->actor)->post("/gudang/barang-masuk/{$ours->public_id}/terima", ReceivingWorld::request(ReceivingWorld::line($ours), $unit->code, $this->rack, '5', more: ['referensi' => 'SJ-KITA']));
    expect(receiptAnswer()['kind'])->toBe('COMMITTED', json_encode(receiptAnswer()));
    $url = "/pembelian/{$ours->public_id}";

    $cutter = Authorization::admin([$this->company->id], ['cost.view', 'purchase.manage', 'projects.view', 'stock.view', 'stock.reverse', 'stock.reserve']);
    $screen = $this->actingAs($cutter)->get($url)->assertInertia(fn (AssertableInertia $page) => $page->where('receipts.0.reversal.may_cut', true)->has('receipts.0.reversal.reservations', 1)->where('receipts.0.reversal.reservations.0.project', null)->etc())->viewData('page');
    expect(json_encode($screen['props']))->not->toContain($project->project_number)->not->toContain($project->public_id);

    $plain = Authorization::admin([$this->company->id], ['cost.view', 'purchase.manage', 'stock.view', 'stock.reverse']);
    $this->actingAs($plain)->get($url)->assertInertia(fn (AssertableInertia $page) => $page->where('receipts.0.reversal.may_cut', false)->where('receipts.0.reversal.reservations', [])->etc());

    $buyer = Authorization::admin([$this->company->id], ['cost.view', 'purchase.manage']);
    $this->actingAs($buyer)->get($url)->assertInertia(fn (AssertableInertia $page) => $page->where('receipts.0.reference', null)->where('receipts.0.lot', null)->etc());
    $this->actingAs($this->actor)->get("/gudang/barang-masuk/{$ours->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('receipts.0.reference', 'SJ-KITA')->etc());
});
