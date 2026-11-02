<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReturnWorld;
use Tests\Support\StockWorld;

// TL-04, TL-06, TL-07, TL-14 (TASK-044; ROUTE-028; ROUTE-176; ROUTE-177; ROUTE-271; NAV-028; AZ-11; SM-01; SM-04): PG-028 by `purchase_return.record`, the form a purchase opens, the return answering on its case, the outcome in place, the close back on PG-028; another company's purchase or case unknown.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->foreign = InvoiceWorld::make();
    $this->returner = ReceivingWorld::actor($this->company, ReturnWorld::CAPABILITIES);
    $this->stocker = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
    $this->lot = StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->unit, $this->rack, '10', '12000');
    $this->purchase = (string) DB::table('purchases')->where('id', ReturnWorld::purchaseOf($this->lot))->value('public_id');
    $this->lotHandle = RowHandle::of('inventory_lots', $this->lot);
});

afterEach(fn () => @unlink($this->hotFile));

function returnPage(object $test, object $viewer, string $query = ''): array
{
    return $test->actingAs($viewer)->get('/gudang/retur-pemasok'.$query)->assertOk()->viewData('page')['props'];
}

function returnBody(object $test, string $qty = '2'): array
{
    return ['command_id' => (string) Str::uuid(), 'lot' => $test->lotHandle, 'unit' => [['rak' => RowHandle::of('locations', $test->rack), 'kondisi' => 'USABLE', 'jumlah' => $qty]], 'alasan' => 'Rusak dari pemasok', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()];
}

it('opens the lot\'s own purchase from the lot\'s "Retur ke Pemasok", another company\'s lot unknown (ADMIN_FLOW §11 CM-09; ROUTE-028)', function () {
    $product = (string) DB::table('products')->where('id', $this->product)->value('public_id');
    $entry = "/gudang/retur-pemasok?produk={$product}&lot={$this->lotHandle}";

    $this->actingAs($this->returner)->get($entry)->assertRedirect("/gudang/retur-pemasok?pembelian={$this->purchase}&lot={$this->lotHandle}");
    $this->actingAs(ReceivingWorld::actor($this->foreign->company, ReturnWorld::CAPABILITIES))->get($entry)->assertNotFound();
    $this->actingAs($this->returner)->get("/gudang/retur-pemasok?produk={$product}&lot=".str_repeat('0', 32))->assertNotFound();
    expect($this->actingAs($this->returner)->get("/gudang/stok/{$product}")->viewData('page')['props']['can']['return'])->toBeTrue()
        ->and($this->actingAs($this->stocker)->get("/gudang/stok/{$product}")->viewData('page')['props']['can']['return'])->toBeFalse();
});

it('opens PG-028 by purchase_return.record alone, and the form a purchase opens lists its lots and the chosen lot\'s units (ROUTE-028; AZ-11; criterion 7)', function () {
    $this->actingAs($this->stocker)->get('/gudang/retur-pemasok')->assertForbidden();
    $this->actingAs($this->stocker)->post("/pembelian/{$this->purchase}/retur", returnBody($this))->assertForbidden();

    $form = returnPage($this, $this->returner, "?pembelian={$this->purchase}&lot={$this->lotHandle}");
    expect($form['list']['rows'])->toBe([])->and($form['can']['record'])->toBeTrue()
        ->and(collect($form['purchase']['lots'])->pluck('lot')->all())->toBe([$this->lotHandle])
        ->and(collect($form['units']['places'])->pluck('qty')->all())->toBe(['10']);
});

it('returns through ROUTE-176 and answers on PG-028 with the case, its figures and its units (NAV-028; criterion 2)', function () {
    $this->actingAs($this->returner)->post("/pembelian/{$this->purchase}/retur", returnBody($this))->assertRedirect();
    $case = (string) DB::table('correction_cases')->where('case_kind', 'PURCHASE_RETURN')->value('public_id');
    $page = returnPage($this, $this->returner, "?kasus={$case}");

    expect($page['case']['state'])->toBe('OPEN')->and($page['case']['figures']['returned'])->toBe($page['case']['figures']['open'])
        ->and(collect($page['case']['units'])->pluck('qty')->all())->toBe(['2'])
        ->and($page['case']['can'])->toMatchArray(['outcome' => true, 'close' => false])
        ->and(collect($page['list']['rows'])->pluck('id')->all())->toBe([$case]);
});

it('records an outcome through ROUTE-177 in place, and closes from PG-028 back to PG-028 once the case balances (INT-177; INT-271; C-46; criterion 6)', function () {
    expect(ReturnWorld::returnUnits($this->returner->id, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '2')]))->toBe('COMMITTED');
    $id = ReturnWorld::lastCase();
    $case = (string) DB::table('correction_cases')->where('id', $id)->value('public_id');
    $returned = ReturnWorld::figures($id)['returned'];

    $this->actingAs($this->returner)->post("/pembelian/{$this->purchase}/retur/{$case}/hasil", ['command_id' => (string) Str::uuid(), 'hasil' => 'UNRECOVERED', 'nilai' => $returned, 'alasan' => 'Pemasok menolak mengganti', 'tanggal' => BusinessDate::today()])
        ->assertRedirect("/gudang/retur-pemasok?kasus={$case}");
    expect(returnPage($this, $this->returner, "?kasus={$case}")['case']['can']['close'])->toBeTrue();

    $lock = (int) DB::table('correction_cases')->where('id', $id)->value('lock_version');
    $this->actingAs($this->returner)->post("/dokumen/kasus/{$case}/tutup", ['command_id' => (string) Str::uuid(), 'lock_version' => $lock, 'alasan' => 'Retur selesai', 'asal' => 'retur'])
        ->assertRedirect("/gudang/retur-pemasok?kasus={$case}");
    expect(DB::table('correction_cases')->where('id', $id)->value('state'))->toBe('CLOSED');
});

it('answers another company\'s purchase or case as unknown (SM-01; RC-05)', function () {
    $foreigner = ReceivingWorld::actor($this->foreign->company, ReturnWorld::CAPABILITIES);
    expect(ReturnWorld::returnUnits($this->returner->id, $this->lot, [AdjustmentWorld::pick($this->lot, $this->rack, '1')]))->toBe('COMMITTED');
    $case = (string) DB::table('correction_cases')->where('id', ReturnWorld::lastCase())->value('public_id');

    $this->actingAs($foreigner)->post("/pembelian/{$this->purchase}/retur", returnBody($this))->assertNotFound();
    $this->actingAs($foreigner)->get("/gudang/retur-pemasok?kasus={$case}")->assertNotFound();
    $this->actingAs($foreigner)->post("/pembelian/{$this->purchase}/retur/{$case}/hasil", ['command_id' => (string) Str::uuid(), 'hasil' => 'REFUND', 'alasan' => 'Menunggu dana', 'tanggal' => BusinessDate::today()])->assertNotFound();
    expect(returnPage($this, $foreigner)['list']['rows'])->toBe([]);
});

it('offers "Retur ke Pemasok" on PG-016 only with its capability (AZ-01; ADMIN_FLOW §11)', function () {
    $viewer = Authorization::admin([$this->company->id], ['cost.view', 'stock.view']);

    expect($this->actingAs($this->returner)->get("/pembelian/{$this->purchase}")->assertOk()->viewData('page')['props']['can']['return'])->toBeTrue()
        ->and($this->actingAs($viewer)->get("/pembelian/{$this->purchase}")->assertOk()->viewData('page')['props']['can']['return'])->toBeFalse();
});
