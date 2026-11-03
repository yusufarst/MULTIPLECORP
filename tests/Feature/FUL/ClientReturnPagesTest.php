<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\ClientReturnWorld;
use Tests\Support\DeliveryWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-04, TL-07 (TASK-048; ROUTE-034; ROUTE-210; ROUTE-211; ROUTE-271; NAV-034; INT-210; INT-211; SM-25; criteria 1, 3, 4, 7, 8): PG-034
// with the shipment lines the client received and what is still with it, a return answering on PG-034 with its case and open
// steps, each refusal in place with its figure, the reversal in place on PG-034 or PG-028 and never elsewhere, "Tutup Kasus"
// answering on PG-034; its entries on PG-011's *Tindakan Lain* and PG-030's delivered record; the contra only with `cost.view`.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, ClientReturnWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->lot = StockWorld::lot($this->actor->id, $this->company, $this->demand['product'], $this->demand['unit'], $this->rack, '10', '1000');
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->demand['line'], $this->demand['unit'], '5', [DispatchWorld::pick($this->lot, $this->rack, '5')]))->toBe('COMMITTED');
    $this->shipment = DispatchWorld::last($this->demand['line']);
    expect(DeliveryWorld::record($this->actor->id, $this->demand['line'], [[$this->shipment->dispatch, '3']], DeliveryWorld::proof($this->company)))->toBe('COMMITTED');
    $this->project = (string) $this->demand['project']->public_id;
    $this->page = "/proyek/{$this->project}/retur";
    $this->proof = (string) DB::table('evidence_documents')->where('id', ClientReturnWorld::proof($this->company))->value('public_id');
});

afterEach(fn () => @unlink($this->hotFile));

function clientReturnBody(object $test, string $qty, array $more = []): array
{
    return ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Klien mengembalikan barang', 'bukti' => $test->proof, 'baris' => RowHandle::of('dispatch_lines', $test->shipment->dispatch),
        'unit' => [['konsumsi' => RowHandle::of('stock_consumptions', $test->shipment->consumption), 'jumlah' => $qty, 'satuan_dasar' => true, 'kondisi' => 'USABLE', 'rak' => RowHandle::of('locations', $test->rack)]], ...$more];
}

function clientReturnAnswer(object $test, string $url, array $body): array
{
    $answer = $test->actingAs($test->actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

it('shows PG-034 with what the client received and what is still with it, then records a return through ROUTE-210 back on PG-034 with its case and open steps (NAV-034; INT-210; PX-08; criteria 1, 3, 4)', function () {
    $this->actingAs($this->actor)->get($this->page)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('FUL/ClientReturn', false)
        ->where('page.lines.0.delivered', '3')->where('page.lines.0.returned', '0')->where('page.lines.0.consumptions.0.open', '3')->where('page.cases', [])
        ->where('page.can.record', true)->where('page.can.reverse', true)->where('page.proofs.0.id', $this->proof));
    Auth::forgetGuards();

    $this->actingAs($this->actor)->post($this->page, clientReturnBody($this, '2'))->assertRedirect($this->page);
    Auth::forgetGuards();

    $this->actingAs($this->actor)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('page.lines.0.delivered', '1')->where('page.lines.0.returned', '2')->where('page.cases.0.state', 'OPEN')
        ->where('page.cases.0.steps', [['key' => 'demand', 'done' => false]])
        ->where('page.cases.0.restorations.0.quantity', '2')->where('page.cases.0.restorations.0.standing', '2')->where('page.cases.0.restorations.0.contra', '2000.00'));
    expect(DeliveryWorld::delivered($this->demand['line']))->toBe('1.000');
});

it('answers each refusal in place with its figure and each missing field at the field: above net delivered, the proof, an unknown line or serial (C-30; MSG-02; MSG-12; IP-14; criterion 1)', function () {
    $over = clientReturnAnswer($this, $this->page, clientReturnBody($this, '4'));

    expect($over['kind'])->toBe('REJECTED')->and($over['code'])->toBe('C-30')->and($over['text'])->toBe(__('returns_client.refusal.delivered', ['sisa' => '3']));

    $this->actingAs($this->actor)->post($this->page, clientReturnBody($this, '1', ['bukti' => null]))->assertSessionHasErrors('bukti');
    $this->actingAs($this->actor)->post($this->page, clientReturnBody($this, '1', ['baris' => RowHandle::of('dispatch_lines', 999999)]))->assertSessionHasErrors('baris');
    $this->actingAs($this->actor)->post($this->page, clientReturnBody($this, '1', ['alasan' => 'x']))->assertSessionHasErrors('alasan');
    $serial = clientReturnBody($this, '1');
    $serial['unit'][0]['seri'] = RowHandle::of('serial_units', 999999);
    $this->actingAs($this->actor)->post($this->page, $serial)->assertSessionHasErrors('unit.0.seri');

    expect(DB::table('stock_restorations')->count())->toBe(0);
});

it('reverses a return in place through ROUTE-211, answering on the page that sent it and never on another address (INT-211; CM-37; C-55; criterion 7)', function () {
    expect(clientReturnAnswer($this, $this->page, clientReturnBody($this, '2'))['kind'])->toBe('COMMITTED');
    $return = ClientReturnWorld::last();
    $movement = (string) DB::table('stock_movements')->where('id', $return->movement)->value('public_id');
    $undo = fn (string $qty, string $from): array => ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Tercatat keliru', 'asal' => $from,
        'unit' => [['pemulihan' => RowHandle::of('stock_restorations', $return->restoration), 'jumlah' => $qty]]];

    $this->actingAs($this->actor)->post("/retur/{$movement}/balikkan", $undo('1', $this->page))->assertRedirect($this->page);
    Auth::forgetGuards();
    $this->actingAs($this->actor)->post("/retur/{$movement}/balikkan", $undo('1', 'https://elsewhere.test/'))->assertRedirect('/gudang/retur-pemasok');
    Auth::forgetGuards();

    expect(clientReturnAnswer($this, "/retur/{$movement}/balikkan", $undo('1', $this->page))['code'])->toBe('C-55')
        ->and(DeliveryWorld::delivered($this->demand['line']))->toBe('3.000');

    $this->actingAs($this->actor)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('page.cases.0.restorations.0.standing', '0'));
    $this->actingAs($this->actor)->post('/retur/'.Str::uuid().'/balikkan', $undo('1', $this->page))->assertNotFound();
});

it('closes the case through ROUTE-271 back on PG-034 (INT-271; NAV-034; BR-CR-04; criterion 4)', function () {
    expect(clientReturnAnswer($this, $this->page, clientReturnBody($this, '1'))['kind'])->toBe('COMMITTED');
    $case = DB::table('correction_cases')->where('case_kind', 'SALES_RETURN')->sole();

    $this->actingAs($this->actor)->post("/dokumen/kasus/{$case->public_id}/tutup", ['command_id' => (string) Str::uuid(), 'lock_version' => $case->lock_version, 'alasan' => 'Retur selesai', 'asal' => 'retur-klien', 'proyek' => $this->project])
        ->assertRedirect($this->page);

    expect(DB::table('correction_cases')->where('id', $case->id)->value('state'))->toBe('CLOSED');
});

it('enters PG-034 from PG-011\'s Tindakan Lain and from PG-030\'s delivered record, only with `sales_return.record` (NAV-034; INFORMATION_ARCHITECTURE §14.3)', function () {
    $this->actingAs($this->actor)->get("/proyek/{$this->project}")->assertInertia(fn (AssertableInertia $page) => $page->where('return_href', $this->page));
    $this->actingAs($this->actor)->get('/gudang/pengiriman?tab=tercatat')->assertInertia(fn (AssertableInertia $page) => $page->where('list.rows.0.return', $this->page));
    Auth::forgetGuards();

    $viewer = ReceivingWorld::actor($this->company, DeliveryWorld::CAPABILITIES);
    $this->actingAs($viewer)->get("/proyek/{$this->project}")->assertInertia(fn (AssertableInertia $page) => $page->where('return_href', null));
    $this->actingAs($viewer)->get('/gudang/pengiriman?tab=tercatat')->assertInertia(fn (AssertableInertia $page) => $page->where('list.rows.0.return', null));
    $this->actingAs($viewer)->get($this->page)->assertForbidden();
});

it('shows a restoration\'s contra only with `cost.view` (SM-25; PJ-05)', function () {
    expect(clientReturnAnswer($this, $this->page, clientReturnBody($this, '1'))['kind'])->toBe('COMMITTED');
    $blind = ReceivingWorld::actor($this->company, ['sales_return.record', 'stock.view', 'projects.view']);

    $this->actingAs($blind)->get($this->page)->assertInertia(fn (AssertableInertia $page) => $page->where('page.can.cost', false)->missing('page.cases.0.restorations.0.contra')->where('page.cases.0.restorations.0.quantity', '1'));
});
