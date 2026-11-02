<?php

use App\Modules\INV\Queries\AdjustmentQueries;
use App\Modules\OPS\Models\CorrectionCase;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-04, TL-06, TL-07, TL-14 (TASK-042; ROUTE-027; ROUTE-190; ROUTE-194–ROUTE-196; W4-13; W4-18; W4-21; W4-22; SM-01; SM-04; SM-25; criteria 16–18): the page's props per viewer, each route with its refusals and its capability, a cost only by family.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, AdjustmentWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->product = $this->demand['product'];
    $this->productId = (string) DB::table('products')->where('id', $this->product)->value('public_id');
    $this->stocker = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES);
    $this->lot = StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->demand['unit'], $this->rack, '10', '10000');
    $this->evidence = (string) DB::table('evidence_documents')->where('id', AdjustmentWorld::evidence())->value('public_id');
    Numbering::goLive(date('Y').'-01-01');
});

afterEach(fn () => @unlink($this->hotFile));

function adjustmentBody(object $test, string $qty = '2', array $extra = []): array
{
    return ['command_id' => (string) Str::uuid(), 'produk' => $test->productId, 'jenis' => 'SHRINKAGE', 'unit' => [['lot' => RowHandle::of('inventory_lots', $test->lot), 'rak' => RowHandle::of('locations', $test->rack), 'kondisi' => 'USABLE', 'jumlah' => $qty]], 'bukti' => [$test->evidence], 'alasan' => 'Susut terhitung', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today(), ...$extra];
}

function adjustmentAnswer(object $test, object $actor, string $url, array $body): array
{
    return $test->actingAs($actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
}

it('opens PG-027 for a holder of either capability and refuses anyone else; each command absent without its own (ROUTE-027; IG-01; SM-04; criterion 18)', function () {
    $condition = Authorization::admin([$this->company->id], ['stock.view', 'stock.condition']);
    $adjust = Authorization::admin([$this->company->id], ['stock.view', 'stock.adjust']);
    $neither = Authorization::admin([$this->company->id], ['stock.view']);

    $can = fn (object $viewer): array => $this->actingAs($viewer)->get("/gudang/penyesuaian?produk={$this->productId}")->assertOk()->viewData('page')['props']['can'];
    expect($can($condition)['condition'])->toBeTrue()->and($can($condition)['adjust'])->toBeFalse()->and($can($adjust)['adjust'])->toBeTrue()->and($can($adjust)['condition'])->toBeFalse();
    $this->actingAs($neither)->get('/gudang/penyesuaian')->assertForbidden();

    $this->actingAs($neither)->post('/gudang/kondisi', [])->assertForbidden();
    $this->actingAs($neither)->post('/gudang/penyesuaian', [])->assertForbidden();
    $this->actingAs($neither)->post("/gudang/stok/{$this->productId}/pindah", [])->assertForbidden();
    $this->actingAs($neither)->post('/gudang/penyesuaian/'.Str::uuid().'/balikkan', [])->assertForbidden();
    $this->actingAs($adjust)->post('/gudang/kondisi', [])->assertForbidden();
    $this->actingAs($condition)->post('/gudang/penyesuaian', [])->assertForbidden();

    $stock = $this->actingAs($neither)->get("/gudang/stok/{$this->productId}")->viewData('page')['props']['can'];
    expect($stock['condition'])->toBeFalse()->and($stock['adjust'])->toBeFalse()->and($stock['move'])->toBeFalse();
});

it('lands a recorded adjustment on PG-019 with its answer, refuses in place, and never takes the bearer from the payload (NAV-027; L-15; CS-04; DP-03; criterion 6)', function () {
    $this->actingAs($this->actor)->post('/gudang/penyesuaian', adjustmentBody($this))->assertRedirect("/gudang/stok/{$this->productId}");
    $consumption = DB::table('stock_consumptions')->where('kind', 'LOSS')->sole();
    expect($consumption->bearer_company_id)->toBe($this->company->id)->and($consumption->bearer_project_id)->toBeNull();

    $over = adjustmentAnswer($this, $this->actor, '/gudang/penyesuaian', adjustmentBody($this, '99'));
    expect($over['code'])->toBe('C-03')->and($over['state']['available'])->toBe('8')->and($over['text'])->toContain('8');
    $this->actingAs($this->actor)->post('/gudang/penyesuaian', adjustmentBody($this, '1', ['alasan' => '']))->assertSessionHasErrors('alasan');
    $this->actingAs($this->actor)->post('/gudang/penyesuaian', adjustmentBody($this, '1', ['bukti' => []]))->assertSessionHasErrors('bukti');
    // There is no increase to send (L-15).
    $this->actingAs($this->actor)->post('/gudang/penyesuaian', adjustmentBody($this, '1', ['jenis' => 'INCREASE']))->assertSessionHasErrors('jenis');
    $this->actingAs($this->actor)->post('/gudang/penyesuaian', adjustmentBody($this, '-1'))->assertSessionHasErrors('unit.0.jumlah');
});

it('asks each lot\'s confirmation of unidentified units at the field (L-42; criterion 4)', function () {
    $body = adjustmentBody($this, '1', ['tanpa_identitas' => true]);
    $this->actingAs($this->actor)->post('/gudang/penyesuaian', $body)->assertSessionHasErrors('unit.0.dikonfirmasi');
    $body['unit'][0]['dikonfirmasi'] = true;
    $this->actingAs($this->actor)->post('/gudang/penyesuaian', $body)->assertRedirect("/gudang/stok/{$this->productId}");
});

it('answers a changed policy CONFLICT, ST-09, on each command (L-35)', function () {
    foreach ([
        ['/gudang/kondisi', adjustmentBody($this, '1', ['aksi' => 'kondisi', 'kebijakan_seri' => true])],
        ['/gudang/penyesuaian', adjustmentBody($this, '1', ['kebijakan_seri' => true])],
        ["/gudang/stok/{$this->productId}/pindah", ['command_id' => (string) Str::uuid(), 'lot' => RowHandle::of('inventory_lots', $this->lot), 'dari' => RowHandle::of('locations', $this->rack), 'ke' => RowHandle::of('locations', ReceivingWorld::rack()), 'kondisi' => 'USABLE', 'jumlah' => '1', 'kebijakan_seri' => true, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()]],
    ] as [$url, $body]) {
        $answer = adjustmentAnswer($this, $this->actor, $url, $body);
        expect($answer['kind'])->toBe('CONFLICT')->and($answer['code'])->toBe('ST-09');
    }
});

it('needs only its capability and one company in scope for a change or move of another company\'s lot, and the bearer in scope for its loss (AZ-08; CS-05; XL-1; UXS-22; criterion 17)', function () {
    $foreign = InvoiceWorld::make();
    $lot = StockWorld::lot(ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id, $foreign->company, $this->product, $this->demand['unit'], $this->rack, '5');
    $unit = ['lot' => RowHandle::of('inventory_lots', $lot), 'rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '1'];

    expect(adjustmentAnswer($this, $this->actor, '/gudang/kondisi', adjustmentBody($this, '1', ['aksi' => 'kondisi', 'unit' => [$unit]]))['kind'])->toBe('COMMITTED');
    $routed = adjustmentAnswer($this, $this->actor, '/gudang/penyesuaian', adjustmentBody($this, '1', ['unit' => [$unit], 'alasan_alokasi' => 'Alasan']));
    expect($routed['code'])->toBe('AZ-08')->and($routed['text'])->toBe(__('messages.msg_14'))
        ->and(DB::table('audit_events')->where('action', 'INV.LossRouted')->where('company_id', $foreign->company->id)->count())->toBe(1)
        ->and(DB::table('stock_consumptions')->where('kind', 'LOSS')->count())->toBe(0);
});

it('offers the units physically, another company\'s with the marker, and a loss\'s amount only with cost.view of its bearer (J-INV-06; PJ-01; PJ-04; PJ-06; SM-25; criterion 16)', function () {
    $foreign = InvoiceWorld::make();
    StockWorld::lot(ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id, $foreign->company, $this->product, $this->demand['unit'], $this->rack, '4', '77777');
    $this->actingAs($this->actor)->post('/gudang/penyesuaian', adjustmentBody($this))->assertRedirect();
    $page = $this->actingAs($this->actor)->get("/gudang/penyesuaian?produk={$this->productId}")->assertOk()->viewData('page')['props'];
    $places = collect($page['units']['places']);

    expect($places->where('other', true))->toHaveCount(1)->and($places->firstWhere('oldest', true)['other'])->toBeFalse()->and(array_keys($places->firstWhere('other', true)))->not->toContain('company');
    $whole = json_encode($page);
    expect($whole)->not->toContain('77777')->not->toContain($foreign->company->legal_name)->not->toContain('remaining_cost');

    $version = (string) $this->actingAs($this->actor)->get("/gudang/penyesuaian?produk={$this->productId}")->viewData('page')['version'];
    $losses = fn (object $viewer): array => $this->actingAs($viewer)->get("/gudang/penyesuaian?produk={$this->productId}", ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'INV/Adjustments', 'X-Inertia-Partial-Data' => 'losses'])->assertOk()->json('props.losses');
    $costing = Authorization::admin([$this->company->id], [...AdjustmentWorld::CAPABILITIES, 'cost.view']);

    expect($losses($this->actor)[0]['amounts'])->toBe([])->and($losses($this->actor)[0]['open'])->toBe('2')
        ->and($losses($costing)[0]['amounts'])->toBe([['company' => $this->company->code, 'amount' => '20000.00']]);

    // Another company's Admin sees neither this loss on PG-027 nor its reason on PG-020 (PJ-05; PJ-06).
    $outsider = ReceivingWorld::actor($foreign->company, AdjustmentWorld::CAPABILITIES);
    expect($losses($outsider))->toBe([]);
    $history = fn (object $viewer): string => json_encode($this->actingAs($viewer)->get('/gudang/riwayat-stok')->assertOk()->viewData('page')['props']['list']);
    expect($history($this->actor))->toContain('Susut terhitung')->and($history($outsider))->not->toContain('Susut terhitung');

    // Nor the reason of its reversal, a movement of the pool like the loss.
    $movement = (string) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->value('public_id');
    expect($this->actingAs($this->actor)->post("/gudang/penyesuaian/{$movement}/balikkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah hitung rak', 'tanggal' => BusinessDate::today()])->isRedirect())->toBeTrue()
        ->and($history($this->actor))->toContain('Salah hitung rak')
        ->and($history($outsider))->not->toContain('Salah hitung rak');
});

it('resolves a foreign serial, an unseen evidence and an unknown lot exactly like nonexistent ones (CS-04; PJ-08; SM-01)', function () {
    $foreign = InvoiceWorld::make();
    $hidden = (string) DB::table('evidence_documents')->where('id', AdjustmentWorld::evidence(['company_id' => $foreign->company->id, 'evidence_scope' => 'COMPANY', 'evidence_type' => 'DELIVERY_PROOF']))->value('public_id');
    $this->actingAs($this->actor)->post('/gudang/penyesuaian', adjustmentBody($this, '1', ['bukti' => [$hidden]]))->assertSessionHasErrors('bukti.0');
    $this->actingAs($this->actor)->post('/gudang/penyesuaian', adjustmentBody($this, '1', ['unit' => [['lot' => str_repeat('a', 32), 'rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '1']]]))->assertSessionHasErrors('unit.0.lot');
    $this->actingAs($this->actor)->get('/gudang/penyesuaian?produk='.Str::uuid())->assertNotFound();
    $this->actingAs($this->actor)->post('/gudang/stok/'.Str::uuid().'/pindah', ['command_id' => (string) Str::uuid()])->assertNotFound();
    $this->actingAs($this->actor)->post('/gudang/penyesuaian/'.Str::uuid().'/balikkan', ['command_id' => (string) Str::uuid()])->assertNotFound();

    $queries = app(AdjustmentQueries::class);
    $found = collect($queries->proofs($this->actor->id)['rows'])->pluck('id')->all();
    expect($found)->toContain($this->evidence)->not->toContain($hidden);
});

it('finds the units a move needs past the first page: a lot with its serials, a serial with its place, nothing unasked (ROUTE-019; INT-190)', function () {
    $code = (string) DB::table('inventory_lots')->where('id', $this->lot)->value('lot_code');
    $other = StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->demand['unit'], $this->rack, '3');
    $version = (string) $this->actingAs($this->actor)->get("/gudang/stok/{$this->productId}")->viewData('page')['version'];
    $found = fn (string $text): array => $this->actingAs($this->actor)->get("/gudang/stok/{$this->productId}?cari_unit=".urlencode($text), ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'INV/ProductStock', 'X-Inertia-Partial-Data' => 'moving_found'])->json('props.moving_found');

    expect(collect($found($code)['places'])->pluck('lot')->all())->toBe([RowHandle::of('inventory_lots', $this->lot)])
        ->and($found('tidak-ada-kode')['places'])->toBe([])
        ->and($this->actingAs($this->actor)->get("/gudang/stok/{$this->productId}")->viewData('page')['props'])->not->toHaveKey('moving_found')
        ->and(collect(app(AdjustmentQueries::class)->screen($this->actor->id, $this->product)['places'])->pluck('lot')->all())->toContain(RowHandle::of('inventory_lots', $other));

    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $serial = StockWorld::serial($this->product, $other, $this->rack);
    $number = (string) DB::table('serial_units')->where('id', $serial)->value('serial_number');
    $bySerial = $found($number);
    expect(collect($bySerial['serials'])->pluck('handle')->all())->toBe([RowHandle::of('serial_units', $serial)])
        ->and(collect($bySerial['places'])->pluck('lot')->all())->toBe([RowHandle::of('inventory_lots', $other)])
        ->and(collect($found((string) DB::table('inventory_lots')->where('id', $other)->value('lot_code'))['serials'])->pluck('handle')->all())->toBe([RowHandle::of('serial_units', $serial)]);
});

it('reaches a case past the newest by its lot or project, and says the list is cut (CM-11; WF-INV-06)', function () {
    $queries = app(AdjustmentQueries::class);
    $lots = [];

    foreach (range(1, AdjustmentQueries::LOSSES + 1) as $index) {
        $lots[] = StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->demand['unit'], $this->rack, '1');
        CorrectionCase::factory()->create(['company_id' => $this->company->id, 'case_kind' => 'DAMAGED_UNIT', 'source_lot_id' => end($lots)]);
    }

    $oldest = (string) DB::table('inventory_lots')->where('id', $lots[0])->value('lot_code');
    $all = $queries->cases($this->actor->id, $this->product);
    $found = $queries->cases($this->actor->id, $this->product, $oldest);

    expect($all['more'])->toBeTrue()->and($all['rows'])->toHaveCount(AdjustmentQueries::LOSSES)->and(collect($all['rows'])->pluck('lot')->all())->not->toContain($oldest)
        ->and($found['more'])->toBeFalse()->and(collect($found['rows'])->pluck('lot')->all())->toBe([$oldest])
        ->and($queries->cases($this->actor->id, $this->product, 'tidak-ada')['rows'])->toBe([]);
});
