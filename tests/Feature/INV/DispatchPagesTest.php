<?php

use App\Modules\INV\Queries\DispatchQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;
use Tests\Support\StockWorld;

// TL-04, TL-06, TL-07, TL-14 (TASK-041; ROUTE-023; ROUTE-024; ROUTE-186–ROUTE-189; PJ-01–PJ-06; SM-01; SM-04; SM-25; IG-01; criteria 17, 18, 21): the pages' props per viewer, each route with its refusals and its capability, never a cost.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->line = $this->demand['line'];
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
    $this->project = DB::table('projects')->where('id', $this->demand['project']->id)->value('public_id');
    $this->lot = StockWorld::lot($this->actor->id, $this->company, $this->product, $this->unit, $this->rack);
    Numbering::goLive(date('Y').'-01-01');
});

afterEach(fn () => @unlink($this->hotFile));

function dispatchBody(object $test, string $qty = '2', array $extra = []): array
{
    $page = $test->actingAs($test->actor)->get("/gudang/barang-keluar/{$test->project}")->assertOk()->viewData('page')['props'];
    $line = $page['lines'][0];

    return ['command_id' => (string) Str::uuid(), 'baris' => $line['handle'], 'satuan' => $line['units'][0]['code'], 'jumlah' => $qty, 'lot' => [['lot' => App\Support\RowHandle::of('inventory_lots', $test->lot), 'rak' => App\Support\RowHandle::of('locations', $test->rack), 'jumlah' => $qty]], 'kebijakan_seri' => $line['serialized'], 'kebijakan_skala' => $line['scale'], 'tanggal' => date('Y-m-d'), ...$extra];
}

it('lists the remaining reservations of ACTIVE projects by company on PG-023, a bad cursor refused and never a silent first page (CALC-02; PF-14; NAV-023)', function () {
    expect(ReservationWorld::reserve($this->actor->id, $this->line, '3'))->toBe('COMMITTED');
    $queries = app(DispatchQueries::class);
    $page = $queries->worklist($this->actor->id, $this->company->id, null);

    expect($page['rows'])->toHaveCount(1)->and($page['rows'][0]['remaining'])->toBe('3')->and($page['rows'][0]['href'])->toBe("/gudang/barang-keluar/{$this->project}")
        ->and($queries->worklist($this->actor->id, $this->company->id, 'bukan-kursor')['cursor_failed'])->toBeTrue();

    $this->actingAs($this->actor)->get('/gudang/barang-keluar')->assertOk()->assertInertia(fn (AssertableInertia $inertia) => $inertia->component('INV/DispatchWorklist', false)->has('groups', 1)->etc());
    DB::table('projects')->where('id', $this->demand['project']->id)->update(['state' => 'COMPLETED_NORMAL']);
    expect($queries->worklist($this->actor->id, $this->company->id, null)['rows'])->toBe([]);
});

it('offers the pool\'s usable lots on PG-024, "Ambil Lebih Dulu" on the oldest, another company\'s by its code, rack and quantity with the marker, and no cost in any prop (PJ-01; PJ-04; SM-25; criterion 18)', function () {
    $foreign = InvoiceWorld::make();
    $other = StockWorld::lot(ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id, $foreign->company, $this->product, $this->unit, $this->rack, '4', '77777');
    StockWorld::conditionChange($this->actor->id, $this->company, $this->product, $this->lot, $this->rack, '1');
    $handle = app(DispatchQueries::class)->screen($this->actor->id, $this->demand['project']->id, $this->company->id)['lines'][0]['handle'];
    $version = (string) $this->actingAs($this->actor)->get("/gudang/barang-keluar/{$this->project}")->viewData('page')['version'];
    $props = $this->actingAs($this->actor)->get("/gudang/barang-keluar/{$this->project}?baris={$handle}", ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'INV/DispatchGoods', 'X-Inertia-Partial-Data' => 'choices'])->assertOk()->json('props.choices');
    $lots = collect($props['lots']);

    expect($lots)->toHaveCount(2)->and($lots->where('oldest', true))->toHaveCount(1)->and($lots->firstWhere('other', true))->toHaveKeys(['lot', 'code', 'location', 'rack', 'qty', 'other', 'oldest'])
        ->and(array_keys($lots->firstWhere('other', true)))->not->toContain('company')->and($lots->firstWhere('oldest', true)['qty'])->toBe('9');
    $whole = json_encode($this->actingAs($this->actor)->get("/gudang/barang-keluar/{$this->project}")->viewData('page')['props']);
    expect($whole)->not->toContain('remaining_cost')->not->toContain('77777')->not->toContain($foreign->company->legal_name)
        ->and($other)->toBeGreaterThan(0);
});

it('records a dispatch through ROUTE-186, answers in place and refuses each way at its line (INT-186; C-04; PX-03; criteria 1, 18)', function () {
    $sent = $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$this->project}/keluarkan", dispatchBody($this));

    $sent->assertRedirect("/gudang/barang-keluar/{$this->project}");
    expect(DB::table('dispatch_lines')->where('project_line_id', $this->line)->value('qty_base'))->toBe('2.000')
        ->and(DB::table('audit_events')->where('action', 'INV.GoodsDispatched')->value('authority_class'))->toBe('ADM');

    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$this->project}/keluarkan", dispatchBody($this, '25'))->assertRedirect();
    expect(DB::table('dispatch_lines')->count())->toBe(1);
    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$this->project}/keluarkan", dispatchBody($this, '2', ['baris' => str_repeat('a', 32)]))->assertSessionHasErrors('baris');
});

it('answers a foreign project, line and movement exactly like a nonexistent one, and takes no bearer or cost from the payload (CS-03; CS-04; C-32; SM-01; criterion 21)', function () {
    $foreign = InvoiceWorld::make();
    $outsider = ReceivingWorld::actor($foreign->company, DispatchWorld::CAPABILITIES);
    $sent = dispatchBody($this);
    $this->actingAs($outsider)->get("/gudang/barang-keluar/{$this->project}")->assertNotFound();
    $this->actingAs($outsider)->get('/gudang/barang-keluar/'.Str::uuid())->assertNotFound();
    $this->actingAs($outsider)->post("/gudang/barang-keluar/{$this->project}/keluarkan", $sent)->assertNotFound();

    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toBe('COMMITTED');
    $movement = DB::table('stock_movements')->where('movement_type', 'DISPATCH')->value('public_id');
    $this->actingAs($outsider)->post("/gudang/barang-keluar/{$movement}/balikkan", [])->assertNotFound();

    // A line of another project of this company is refused like one that does not exist.
    $other = DispatchWorld::demand($this->world);
    $body = dispatchBody($this, '1', ['baris' => App\Support\RowHandle::of('project_lines', $other['line'])]);
    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$this->project}/keluarkan", $body)->assertSessionHasErrors('baris');
});

it('keeps the pages and commands to their capabilities: stock.dispatch, stock.reverse, stock.reserve for the offer (IG-01; W4-13; W4-18; SM-04; criterion 18)', function () {
    $without = Authorization::admin([$this->company->id], ['stock.view', 'projects.view']);
    $this->actingAs($without)->get('/gudang/barang-keluar')->assertForbidden();
    $this->actingAs($without)->get("/gudang/barang-keluar/{$this->project}")->assertForbidden();
    $this->actingAs($without)->post("/gudang/barang-keluar/{$this->project}/keluarkan", [])->assertForbidden();

    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toBe('COMMITTED');
    $movement = DB::table('stock_movements')->where('movement_type', 'DISPATCH')->value('public_id');
    $dispatcher = Authorization::admin([$this->company->id], ['stock.view', 'projects.view', 'stock.dispatch']);
    $page = $this->actingAs($dispatcher)->get("/gudang/barang-keluar/{$this->project}")->assertOk()->viewData('page')['props'];
    expect($page['can']['reverse'])->toBeFalse()->and($page['dispatches'][0]['reversible'])->toBeFalse();
    $this->actingAs($dispatcher)->post("/gudang/barang-keluar/{$movement}/balikkan", [])->assertForbidden();
    $this->actingAs($dispatcher)->post("/gudang/barang-keluar/{$movement}/ganti-identitas", [])->assertForbidden();

    // Without `stock.reserve` the restoration's offer is refused, the restoration itself allowed.
    $reverser = Authorization::admin([$this->company->id], ['stock.view', 'projects.view', 'stock.reverse']);
    $row = DispatchWorld::last($this->line);
    expect(DispatchWorld::restore($reverser->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($row->consumption, $this->rack, '1')], '1'))->toContain('AZ-01');
    expect(DispatchWorld::restore($reverser->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($row->consumption, $this->rack, '1')]))->toBe('COMMITTED');
});

it('never offers a drop-ship or service line and refuses it at the route; a closed project takes no dispatch (BR-INV-11; L-11; criterion 17)', function () {
    $dropship = $this->world->project(['DROPSHIP'], '5');
    $line = (int) DB::table('project_lines')->where('project_id', $dropship->id)->value('id');
    $public = DB::table('projects')->where('id', $dropship->id)->value('public_id');
    expect($this->actingAs($this->actor)->get("/gudang/barang-keluar/{$public}")->assertOk()->viewData('page')['props']['lines'])->toBe([]);
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toContain('BR-INV-11');

    DB::table('projects')->where('id', $this->demand['project']->id)->update(['state' => 'COMPLETED_NORMAL']);
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toContain('L-11');
});

it('opens the Surat Jalan\'s draft from the committed dispatch, no second delivery and no stock changed; a dispatch wholly restored has none (DOC-03; L-19; AX-15; criterion 14)', function () {
    Numbering::scheme($this->company, App\Modules\ORG\Enums\SequenceType::Doc03, date('Y').'-01-01', 'YEAR', 'UJI/SJ/{tahun}/{nomor:4}');
    Database\Factories\DOC\DocumentTypeFactory::ensure('DOC-03');
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '2', [DispatchWorld::pick($this->lot, $this->rack, '2')]))->toBe('COMMITTED');
    $movement = (string) DB::table('stock_movements')->where('movement_type', 'DISPATCH')->value('public_id');
    $before = DB::table('stock_product_balances as b')->where('product_id', $this->product)->value('on_hand_qty');

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-03', 'subjek' => ['dispatch_movement_id' => $movement]])->assertRedirect();

    $document = DB::table('documents')->whereNotNull('dispatch_movement_id')->first();
    expect($document)->not->toBeNull()->and((int) $document->dispatch_movement_id)->toBe((int) DB::table('stock_movements')->where('public_id', $movement)->value('id'))
        ->and(DB::table('stock_product_balances as b')->where('product_id', $this->product)->value('on_hand_qty'))->toBe($before)
        ->and(DB::table('stock_movements')->where('movement_type', 'DISPATCH')->count())->toBe(1);
});
