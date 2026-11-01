<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;
use Tests\Support\StockWorld;

// TL-06, TL-14 (TASK-040; AC-13; PJ-01; W4-18; AZ-10; AZ-11; SM-01; SM-04; SM-23; IG-01; criteria 2, 13): `stock.view` pool-wide and its absence, `stock.reverse` as ADM_PLUS, a foreign movement or product answered like none.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, StockWorld::CAPABILITIES);
    $goods = Projects::product();
    $this->product = $goods['product'];
    $this->unit = $goods['base']->id;
    $this->rack = ReceivingWorld::rack();
    $this->lot = StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack);
    $this->change = StockWorld::conditionChange($this->actor->id, $this->company, $this->product->id, $this->lot, $this->rack, '2');
    $this->public = DB::table('stock_movements')->where('id', $this->change)->value('public_id');
    $this->body = fn (): array => ['command_id' => (string) Str::uuid(), 'alasan' => 'Keliru dicatat', 'tanggal' => date('Y-m-d')];
});

afterEach(fn () => @unlink($this->hotFile));

it('denies an account without stock.view every pooled view, whatever its company grants (AC-13; criterion 2)', function () {
    $without = Authorization::admin([$this->company->id], ['projects.view', 'cost.view', 'purchase.manage', 'finance.view']);

    foreach (['/gudang/stok', "/gudang/stok/{$this->product->public_id}", '/gudang/riwayat-stok'] as $url) {
        $this->actingAs($without)->get($url)->assertForbidden();
    }
});

it('reads the pool with stock.view and no company at all (CS-05; AC-13)', function () {
    $pool = Authorization::admin([], ['stock.view']);

    $this->actingAs($pool)->get('/gudang/stok')->assertOk();
    $this->actingAs($pool)->get("/gudang/stok/{$this->product->public_id}")->assertOk();
    $rows = $this->actingAs($pool)->get('/gudang/riwayat-stok')->assertOk()->viewData('page')['props']['list']['rows'];
    expect($rows)->toBe([]);
});

it('refuses ROUTE-212 without stock.reverse, "Balikkan" absent; with it the audit is ADM_PLUS for the Owner\'s review (W4-18; AZ-10; IG-01; criterion 13)', function () {
    $without = Authorization::admin([$this->company->id], ['stock.view']);
    $this->actingAs($without)->post("/gudang/riwayat-stok/{$this->public}/balikkan", ($this->body)())->assertForbidden();
    $rows = collect($this->actingAs($without)->get('/gudang/riwayat-stok')->viewData('page')['props']['list']['rows']);
    expect($rows->where('reversible', true))->toHaveCount(0)->and($rows->whereNotNull('receipt'))->toHaveCount(0);

    $this->actingAs($this->actor)->post("/gudang/riwayat-stok/{$this->public}/balikkan", ($this->body)())->assertRedirect('/gudang/riwayat-stok');
    $audit = DB::table('audit_events')->where('action', 'INV.MovementReversed')->sole();
    expect($audit->authority_class)->toBe('ADM_PLUS')->and($audit->reason)->toBe('Keliru dicatat')->and($audit->company_id)->toBe($this->company->id);
});

it('answers a foreign movement and an unknown one identically (SM-01; SM-23; AZ-11)', function () {
    $other = InvoiceWorld::make()->company;
    $outsider = ReceivingWorld::actor($other, StockWorld::CAPABILITIES);
    $found = $this->actingAs($outsider)->post("/gudang/riwayat-stok/{$this->public}/balikkan", ($this->body)());
    $unknown = $this->actingAs($outsider)->post('/gudang/riwayat-stok/'.Str::uuid().'/balikkan', ($this->body)());

    $found->assertNotFound();
    $unknown->assertNotFound();
    expect(DB::table('stock_movements')->where('reverses_movement_id', $this->change)->count())->toBe(0);
    $this->actingAs($outsider)->get('/gudang/stok/'.Str::uuid())->assertNotFound();
});

it('offers another company\'s reservation by its quantity and marker alone, and refuses its cut (row 61; PJ-04; AZ-08)', function () {
    $foreign = InvoiceWorld::make();
    $project = $foreign->project(['WAREHOUSE'], '20');
    $demand = DB::table('project_lines')->where('project_id', $project->id)->first();
    $unit = (int) DB::table('product_units')->where('product_id', $demand->product_id)->where('is_base', true)->value('unit_id');
    $foreignActor = ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES);
    StockWorld::lot($foreignActor->id, $foreign->company, (int) $demand->product_id, $unit, $this->rack, '5');
    expect(ReservationWorld::reserve($foreignActor->id, $demand->id, '5'))->toBe('COMMITTED');
    $public = DB::table('products')->where('id', $demand->product_id)->value('public_id');

    $row = $this->actingAs($this->actor)->get("/gudang/stok/{$public}")->viewData('page')['props']['reservations']['rows'][0];
    expect($row['reserved'])->toBe('5')->and($row['project'])->toBeNull()->and($row['other'])->toBeTrue();
    expect(json_encode($this->actingAs($this->actor)->get("/gudang/stok/{$public}")->viewData('page')['props']))->not->toContain($project->project_number);

    // A re-inspection whose reversal would lower usable supply below that company's reservation: the cut refused and routed, nothing recorded (AZ-08; IP-20).
    $product = (int) $demand->product_id;
    $lot = StockWorld::lot($this->actor->id, $this->company, $product, $unit, $this->rack, '3');
    StockWorld::conditionChange($this->actor->id, $this->company, $product, $lot, $this->rack, '2');
    $inspection = StockWorld::conditionChange($this->actor->id, $this->company, $product, $lot, $this->rack, '2', 'UNUSABLE');
    expect(ReservationWorld::reserve($foreignActor->id, $demand->id, '3'))->toBe('COMMITTED');
    $movement = DB::table('stock_movements')->where('id', $inspection)->value('public_id');
    $cut = $this->actingAs($this->actor)->get('/gudang/riwayat-stok?gerak='.$movement, ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $this->actingAs($this->actor)->get('/gudang/riwayat-stok')->viewData('page')['version'], 'X-Inertia-Partial-Component' => 'INV/StockHistory', 'X-Inertia-Partial-Data' => 'cut'])->json('props.cut');
    expect($cut['movement'])->toBe($movement)->and($cut['shortfall'])->toBe('2');
    $handle = $cut['reservations'][0]['id'];
    $refused = $this->actingAs($this->actor)->post("/gudang/riwayat-stok/{$movement}/balikkan", [...($this->body)(), 'potong' => [['reservasi' => $handle, 'jumlah' => '2']]]);
    expect($refused->getSession()->get('inertia.flash_data.answer')['code'] ?? $refused->getSession()->get('flash.answer.code') ?? 'seen')->not->toBe('COMMITTED')
        ->and(DB::table('stock_movements')->where('reverses_movement_id', $inspection)->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'INV.ReservationCutRouted')->where('company_id', $foreign->company->id)->count())->toBe(1);
});
