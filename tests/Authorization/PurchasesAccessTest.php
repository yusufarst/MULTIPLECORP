<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\PurchaseWorld;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-037; AZ-01; AZ-11; SM-01; SM-04; SM-25; PJ-09; PJ-15; PJ-18; CS-04; W4-13; W4-18; W4-19; W4-23; criterion 16): `purchase.manage` with its `cost.view` prerequisite, `purchase.cancel`, `purchase.close_remainder` and `purchase.correct_cost` separately granted; `documents.issue` for the PO; `finance.view` for the disbursements part; a foreign record answers like none.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = PurchaseWorld::numbered($this->world->company);
    $this->actor = PurchaseWorld::actor($this->company);
    $this->supplier = PurchaseWorld::supplier();
    $this->goods = Projects::product();
    $this->today = BusinessDate::today();
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods, '5', '1000')], ['perusahaan' => $this->company->public_id]));
    $this->purchase = PurchaseWorld::latest($this->company->id);
    $this->line = PurchaseWorld::lines($this->purchase)[0];
    $this->reasoned = fn (array $more = []): array => ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'alasan' => 'Salah catat', ...$more];
});

afterEach(fn () => @unlink($this->hotFile));

it('denies a capability whose catalogue prerequisite is not granted: purchase.manage without cost.view (AZ-01)', function () {
    $without = Authorization::admin([$this->company->id], ['projects.view', 'purchase.manage']);

    $this->actingAs($without)->get('/pembelian/baru')->assertForbidden();
    $this->actingAs($without)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods)], ['perusahaan' => $this->company->public_id]))->assertForbidden();
    expect(DB::table('purchases')->count())->toBe(1);
});

it('grants each ADM+ correction apart, each route forbidden and its control absent without it (W4-18; W4-19; W4-23; SM-04; IG-01)', function (string $capability, string $path, string $control) {
    $granted = Authorization::admin([$this->company->id], ['cost.view', $capability]);
    $other = Authorization::admin([$this->company->id], ['cost.view', 'purchase.manage']);
    $url = "/pembelian/{$this->purchase->public_id}/{$path}";

    $this->actingAs($other)->post($url, ($this->reasoned)())->assertForbidden();
    $this->actingAs($other)->get("/pembelian/{$this->purchase->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where("can.{$control}", false)->etc());
    $this->actingAs($granted)->get("/pembelian/{$this->purchase->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where("can.{$control}", true)->etc());
})->with([
    'cancel' => ['purchase.cancel', 'batalkan', 'cancel'],
    'close' => ['purchase.close_remainder', 'tutup-sisa', 'close'],
    'correct' => ['purchase.correct_cost', 'koreksi-biaya', 'correct'],
]);

it('answers a purchase of another company like none, reading and writing (SM-01; AZ-11; CS-04)', function () {
    $other = InvoiceWorld::make();
    PurchaseWorld::numbered($other->company);
    $stranger = PurchaseWorld::actor($other->company);
    $url = "/pembelian/{$this->purchase->public_id}";

    $this->actingAs($stranger)->get($url)->assertNotFound();
    $this->actingAs($stranger)->put($url, ['command_id' => (string) Str::uuid(), 'lock_version' => 1, 'baris' => [PurchaseWorld::line($this->goods)]])->assertNotFound();
    $this->actingAs($stranger)->post("{$url}/batalkan", ($this->reasoned)())->assertNotFound();
    $this->actingAs($stranger)->post("{$url}/tutup-sisa", ($this->reasoned)(['baris' => [['baris' => RowHandle::of('purchase_lines', $this->line->id), 'jumlah' => '1']]]))->assertNotFound();
    $this->actingAs($stranger)->post("{$url}/koreksi-biaya", ($this->reasoned)(['jenis' => 'PRICE_CORRECTION', 'catatan' => RowHandle::of('purchase_lines', $this->line->id), 'nilai' => '1']))->assertNotFound();
    $this->actingAs($stranger)->post("{$url}/biaya", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'tagihan' => PurchaseWorld::bill($other->company->id), 'klasifikasi' => 'NON_ATTRIBUTABLE', 'jumlah' => '1'])->assertNotFound();
    $this->actingAs($stranger)->post("{$url}/baris/".RowHandle::of('purchase_lines', $this->line->id).'/terlaksana', ['command_id' => (string) Str::uuid()])->assertNotFound();
    expect($this->purchase->fresh()->state)->toBe('OPEN');
});

it('keeps PG-015 to one company at a time, no other company\'s row or total (PF-13; CS-07; CS-08)', function () {
    $other = InvoiceWorld::make();
    PurchaseWorld::numbered($other->company);
    $both = Authorization::admin([$this->company->id, $other->company->id], PurchaseWorld::CAPABILITIES);
    $this->actingAs($both)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods)], ['perusahaan' => $other->company->public_id]));

    $this->actingAs($this->actor)->get('/pembelian')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.number', $this->purchase->purchase_number)->has('branches', 1)->missing('total')->etc());
    $this->actingAs($both)->get("/pembelian?perusahaan={$other->company->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('branch', $other->company->public_id)->has('branches', 2)->etc());
});

it('shows disbursements only with finance.view and the PO only with documents.issue (W4-13; row 114; PJ-15)', function () {
    $costOnly = Authorization::admin([$this->company->id], ['cost.view']);
    $withFinance = Authorization::admin([$this->company->id], ['cost.view', 'finance.view']);

    $this->actingAs($costOnly)->get("/pembelian/{$this->purchase->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('disbursements', null)->where('can.issue', false)->etc());
    $this->actingAs($withFinance)->get("/pembelian/{$this->purchase->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('disbursements', [])->etc());
    $this->actingAs($costOnly)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-02', 'subjek' => ['purchase_id' => $this->purchase->public_id]])->assertForbidden();
    $this->actingAs($this->actor)->get("/pembelian/{$this->purchase->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.issue', true)->etc());
});

it('answers previous prices per company, never pooled, and a foreign company\'s none (PJ-09; SM-25)', function () {
    $other = InvoiceWorld::make();
    PurchaseWorld::numbered($other->company);
    $stranger = PurchaseWorld::actor($other->company);
    $product = $this->goods['product']->public_id;
    $query = "pemasok={$this->supplier->public_id}&harga={$product}";

    $this->actingAs($stranger)->get("/pembelian/baru?perusahaan={$this->company->public_id}&{$query}")->assertInertia(fn (AssertableInertia $page) => $page->where('previous', [])->where('company', null)->etc());
    $this->actingAs($this->actor)->get("/pembelian/baru?perusahaan={$this->company->public_id}&{$query}")->assertInertia(fn (AssertableInertia $page) => $page->where("previous.{$product}.price", '1000.00')->etc());
});

it('never accepts company, number, state or posted values from the payload (DP-03; WS-02)', function () {
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods, '2', '10')], ['perusahaan' => $this->company->public_id, 'company_id' => 999, 'purchase_number' => 'PALSU', 'state' => 'CANCELLED', 'posted_goods_value' => '9']));
    $saved = PurchaseWorld::latest($this->company->id);

    expect($saved->purchase_number)->not->toBe('PALSU')->and($saved->state)->toBe('OPEN')->and($saved->company_id)->toBe($this->company->id);
});
