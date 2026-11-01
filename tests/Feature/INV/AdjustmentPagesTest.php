<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Queries\AdjustmentQueries;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\Auth;
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

// TL-04, TL-06, TL-07, TL-08, TL-14 (TASK-042; ROUTE-027; ROUTE-190; ROUTE-194–ROUTE-196; INT-190; INT-194–INT-196; W4-13; W4-18; W4-21; W4-22; SM-01; SM-04; SM-25; criteria 16–19): the page's props per viewer, each route with its refusals and its capability, one command_id replayed, a cost only by family.

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

it('answers each command per INT-195, INT-190 and INT-196, one command_id replayed with one effect, a changed payload CONFLICT, after re-authentication too (CI-05; M-09; criterion 19)', function () {
    $condition = ['command_id' => (string) Str::uuid(), 'aksi' => 'kondisi', 'produk' => $this->productId, 'unit' => [['lot' => RowHandle::of('inventory_lots', $this->lot), 'rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '1']], 'bukti' => [$this->evidence], 'alasan' => 'Penyok', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()];
    $twice = function (string $url, array $body): array {
        $first = adjustmentAnswer($this, $this->actor, $url, $body);
        Auth::forgetGuards();

        return [$first, adjustmentAnswer($this, $this->actor, $url, $body)];
    };

    [$first, $replay] = $twice('/gudang/kondisi', $condition);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_movements')->where('movement_type', 'CONDITION_CHANGE')->count())->toBe(1)
        ->and(adjustmentAnswer($this, $this->actor, '/gudang/kondisi', [...$condition, 'alasan' => 'Alasan lain'])['kind'])->toBe('CONFLICT');

    $check = [...$condition, 'command_id' => (string) Str::uuid(), 'aksi' => 'periksa', 'bukti' => []];
    $check['unit'][0]['kondisi'] = 'UNUSABLE';
    expect(adjustmentAnswer($this, $this->actor, '/gudang/kondisi', $check)['kind'])->toBe('COMMITTED');

    $other = ReceivingWorld::rack();
    $move = ['command_id' => (string) Str::uuid(), 'lot' => RowHandle::of('inventory_lots', $this->lot), 'dari' => RowHandle::of('locations', $this->rack), 'ke' => RowHandle::of('locations', $other), 'kondisi' => 'USABLE', 'jumlah' => '2', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()];
    [$first, $replay] = $twice("/gudang/stok/{$this->productId}/pindah", $move);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_movements')->where('movement_type', 'LOCATION_MOVE')->count())->toBe(1);

    $adjust = adjustmentBody($this);
    [$first, $replay] = $twice('/gudang/penyesuaian', $adjust);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_consumptions')->where('kind', 'LOSS')->count())->toBe(1)
        ->and(adjustmentAnswer($this, $this->actor, '/gudang/penyesuaian', [...$adjust, 'alasan' => 'Lain'])['kind'])->toBe('CONFLICT');

    $movement = (string) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->value('public_id');
    $reverse = ['command_id' => (string) Str::uuid(), 'alasan' => 'Keliru dicatat', 'tanggal' => BusinessDate::today()];
    [$first, $replay] = $twice("/gudang/penyesuaian/{$movement}/balikkan", $reverse);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_restorations')->where('kind', 'LOSS_REVERSAL')->count())->toBe(1);
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

    expect($losses($this->actor)[0]['amount'])->toBeNull()->and($losses($this->actor)[0]['open'])->toBe('2')
        ->and($losses($costing)[0]['amount'])->toBe('20000.00');

    // Another company's Admin sees neither this loss on PG-027 nor its reason on PG-020 (PJ-05; PJ-06).
    $outsider = ReceivingWorld::actor($foreign->company, AdjustmentWorld::CAPABILITIES);
    expect($losses($outsider))->toBe([]);
    $history = fn (object $viewer): string => json_encode($this->actingAs($viewer)->get('/gudang/riwayat-stok')->assertOk()->viewData('page')['props']['list']);
    expect($history($this->actor))->toContain('Susut terhitung')->and($history($outsider))->not->toContain('Susut terhitung');
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

it('reads PG-027 within QB-05, the same for one lot and many (PFM-01; PFM-02)', function () {
    $queries = app(AdjustmentQueries::class);
    app(EffectiveCapabilities::class)->for($this->actor->id);
    $statements = function (callable $read): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $read();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $one = $statements(fn () => $queries->screen($this->actor->id, $this->product));
    StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->demand['unit'], $this->rack, '2');
    StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->demand['unit'], $this->rack, '2');

    expect($statements(fn () => $queries->screen($this->actor->id, $this->product)))->toBe($one)->and($one)->toBeLessThanOrEqual(4);
});
