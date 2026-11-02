<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Queries\DiscrepancyQueries;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\CountWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-04, TL-06, TL-07, TL-14 (TASK-043; ROUTE-026; ROUTE-029; ROUTE-191–ROUTE-193; ROUTE-197–ROUTE-200; ROUTE-215; OD-08–OD-10; PJ-07; PJ-22; SM-01; SM-04; SM-25; criteria 6, 8, 10): the pages per viewer, each route by its capability, an Owner-only command refused to an Admin also when forged, the candidates by PJ-07.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->foreign = InvoiceWorld::make();
    $this->counter = ReceivingWorld::actor($this->company, ['stock.view', 'stock.count']);
    $this->applier = ReceivingWorld::actor($this->company, CountWorld::CAPABILITIES);
    $this->stocker = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
    $this->productId = (string) DB::table('products')->where('id', $this->product)->value('public_id');
    $this->rackHandle = RowHandle::of('locations', $this->rack);
    $this->lot = StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->unit, $this->rack, '10', '10000');
    $this->evidence = AdjustmentWorld::evidence();
    $this->owner = Authorization::owner();
});

afterEach(fn () => @unlink($this->hotFile));

function countPageAcross(object $test): int
{
    $other = StockWorld::lot(ReceivingWorld::actor($test->foreign->company, DispatchWorld::CAPABILITIES)->id, $test->foreign->company, $test->product, $test->unit, $test->rack, '5', '12000');
    $count = CountWorld::open($test->applier->id, $test->product, $test->rack);
    expect(CountWorld::line($test->applier->id, $count, $test->product, $test->rack, '13'))->toBe('COMMITTED')
        ->and(CountWorld::apply($test->applier->id, CountWorld::entry($count, pending: '2', evidence: [$test->evidence])))->toBe('COMMITTED');

    return $other;
}

function countPageProps(object $test, object $viewer, string $url = '/gudang/selisih-temuan'): array
{
    return $test->actingAs($viewer)->get($url)->assertOk()->viewData('page')['props'];
}

it('opens PG-026 by stock.count and applies by stock.count_apply alone; each refused otherwise, the application\'s control absent (ROUTE-026; RG-06; IG-01; SM-04)', function () {
    $this->actingAs($this->stocker)->get('/gudang/stok-opname')->assertForbidden();
    expect(countPageProps($this, $this->counter, '/gudang/stok-opname')['can'])->toMatchArray(['count' => true, 'apply' => false])
        ->and(countPageProps($this, $this->applier, '/gudang/stok-opname')['can'])->toMatchArray(['count' => true, 'apply' => true]);

    $count = CountWorld::open($this->counter->id, $this->product, $this->rack);
    $public = (string) DB::table('stock_counts')->where('id', $count)->value('public_id');
    $body = ['command_id' => (string) Str::uuid(), 'alasan' => 'Selisih', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()];
    $this->actingAs($this->counter)->post("/gudang/stok-opname/{$public}/terapkan", $body)->assertForbidden();
    $this->actingAs($this->stocker)->post('/gudang/stok-opname', ['command_id' => (string) Str::uuid(), 'produk' => $this->productId, 'rak' => $this->rackHandle, 'kondisi' => 'USABLE'])->assertForbidden();
    $this->actingAs($this->stocker)->post("/gudang/stok-opname/{$public}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Keliru'])->assertForbidden();
});

it('answers in place with the count, shows the lots physically, another company\'s by its marker alone, and no cost in any prop (NAV-026; PJ-01; PJ-04; SM-25)', function () {
    $foreignLot = StockWorld::lot(ReceivingWorld::actor($this->foreign->company, DispatchWorld::CAPABILITIES)->id, $this->foreign->company, $this->product, $this->unit, $this->rack, '5', '12000');
    $this->actingAs($this->counter)->post('/gudang/stok-opname', ['command_id' => (string) Str::uuid(), 'produk' => $this->productId, 'rak' => $this->rackHandle, 'kondisi' => 'USABLE'])->assertRedirect();
    $public = (string) DB::table('stock_counts')->orderByDesc('id')->value('public_id');
    $page = $this->actingAs($this->applier)->get("/gudang/stok-opname?hitungan={$public}")->assertOk()->viewData('page')['props'];
    $places = collect($page['count']['places']);

    expect($page['count']['state'])->toBe('OPEN')->and($page['count']['across'])->toBeTrue()
        ->and($places->firstWhere('lot', RowHandle::of('inventory_lots', $foreignLot))['other'])->toBeTrue()
        ->and($places->firstWhere('lot', RowHandle::of('inventory_lots', $this->lot))['other'])->toBeFalse()
        ->and(json_encode($page['count']))->not->toContain('10000')->not->toContain('12000')->not->toContain($this->foreign->company->code);
});

it('refuses the Owner\'s commands to an Admin, the route and the action alike, also forged or replayed (OD-08; OD-09; RG-02; SM-04; criterion 6)', function () {
    countPageAcross($this);
    $case = DB::table('unattributed_loss_cases')->sole();
    $admin = Authorization::admin([$this->company->id, $this->foreign->company->id], [...CountWorld::CAPABILITIES, 'stock.resolve_unattributed_evidence', 'stock.allocate_intercompany']);
    $this->actingAs($admin)->post("/gudang/selisih-temuan/{$case->public_id}/tetapkan", ['command_id' => (string) Str::uuid(), 'baris' => [['perusahaan' => $this->company->public_id, 'tutup' => [['lot' => RowHandle::of('inventory_lots', $this->lot), 'jumlah' => '1']]]], 'alasan' => 'Palsu', 'tanggal' => BusinessDate::today()])->assertNotFound();
    expect(fn () => CountWorld::resolve($admin->id, (int) $case->id, [['company' => $this->company->id, 'closes' => [['lot' => $this->lot, 'qty' => '1']]]]))->toThrow(AuthorizationException::class);
    expect(DB::table('unattributed_loss_resolutions')->count())->toBe(0);

    $page = countPageProps($this, $admin);
    expect($page['can'])->toMatchArray(['resolve' => false, 'surplus' => false, 'evidence' => true]);
    $this->actingAs($this->counter)->get('/gudang/selisih-temuan')->assertNotFound();
});

it('never shows a non-Owner the number or identity of other candidates: a cost.view holder its own rows, an evidence holder its scope\'s lots without cost, the Owner all (PJ-07; PJ-22; SM-25; criterion 10)', function () {
    countPageAcross($this);
    $case = fn (object $viewer): array => countPageProps($this, $viewer)['cases']['rows'][0];
    $costing = Authorization::admin([$this->company->id], ['stock.view', 'evidence.view', 'cost.view', 'stock.resolve_unattributed_evidence']);
    $plain = Authorization::admin([$this->company->id], ['stock.view', 'evidence.view', 'stock.resolve_unattributed_evidence']);
    $code = $this->company->code;
    $foreignCode = $this->foreign->company->code;

    $own = $case($costing);
    expect(collect($own['exposures'])->pluck('company')->all())->toBe([$code])
        ->and(collect($own['candidates'])->pluck('company')->unique()->values()->all())->toBe([$code])
        ->and($own['candidates'][0]['cost'])->toBe('10000.00')
        ->and(json_encode($own))->not->toContain($foreignCode);

    $lots = $case($plain);
    expect($lots['exposures'])->toBe([])->and($lots['candidates'][0]['cost'])->toBeNull()
        ->and(json_encode($lots))->not->toContain($foreignCode)->not->toContain('12000')->not->toContain('10000.00');

    $all = $case($this->owner);
    expect(collect($all['exposures'])->pluck('company')->sort()->values()->all())->toEqual(collect([$code, $foreignCode])->sort()->values()->all())
        ->and($all['pending'])->toBe('2');
});

it('resolves through ROUTE-198 and reverses through ROUTE-200, the answer in place (NAV-029)', function () {
    $other = countPageAcross($this);
    $case = DB::table('unattributed_loss_cases')->sole();
    $this->actingAs($this->owner)->post("/gudang/selisih-temuan/{$case->public_id}/tetapkan", ['command_id' => (string) Str::uuid(), 'baris' => [['perusahaan' => $this->foreign->company->public_id, 'tutup' => [['lot' => RowHandle::of('inventory_lots', $other), 'jumlah' => '2']]]], 'alasan' => 'Keputusan Owner', 'tanggal' => BusinessDate::today()])->assertRedirect('/gudang/selisih-temuan');
    expect((string) DB::table('unattributed_loss_cases')->where('id', $case->id)->value('pending_qty_base'))->toBe('0.000');
    $resolution = countPageProps($this, $this->owner)['resolutions']['rows'][0];
    $answer = $this->actingAs($this->owner)->followingRedirects()->post("/gudang/selisih-temuan/{$resolution['id']}/balikkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Keliru', 'tanggal' => BusinessDate::today()])->viewData('page')['flash']['answer'];

    expect($answer['kind'])->toBe('COMMITTED', json_encode($answer))->and((string) DB::table('unattributed_loss_cases')->where('id', $case->id)->value('pending_qty_base'))->toBe('2.000')
        ->and(countPageProps($this, $this->owner)['resolutions']['rows'])->toBe([]);
});

it('resolves a foreign count, line and finding exactly like nonexistent ones, and a bad handle at its field (CS-04; SM-01)', function () {
    $this->actingAs($this->applier)->get('/gudang/stok-opname?hitungan='.Str::uuid())->assertNotFound();
    $this->actingAs($this->counter)->post('/gudang/stok-opname/'.Str::uuid().'/baris', ['command_id' => (string) Str::uuid()])->assertNotFound();
    $this->actingAs($this->owner)->post('/gudang/selisih-temuan/'.str_repeat('a', 32).'/surplus', ['command_id' => (string) Str::uuid()])->assertNotFound();
    $this->actingAs($this->owner)->post('/gudang/selisih-temuan/'.Str::uuid().'/tetapkan', ['command_id' => (string) Str::uuid()])->assertNotFound();
    $this->actingAs($this->counter)->post('/gudang/stok-opname', ['command_id' => (string) Str::uuid(), 'produk' => $this->productId, 'rak' => str_repeat('a', 32), 'kondisi' => 'USABLE'])->assertSessionHasErrors('rak');
});

it('places the print control and the commands per viewer, and the links to the pages (INT-217; IA §8.3; RC-06)', function () {
    $source = file_get_contents(resource_path('js/Pages/INV/StockCounts.tsx'));
    expect($source)->toContain('window.print()')->toContain('print:hidden')
        ->and(file_get_contents(resource_path('js/Pages/INV/ProductStock.tsx')))->toContain('/gudang/stok-opname?produk=')
        ->and(file_get_contents(resource_path('js/lib/navigation.ts')))->toContain("href: '/gudang/selisih-temuan'");
});

it('keeps a pending case out of the pool\'s company figures: the viewer\'s exposure only (PJ-22; MSG-41; criterion 10)', function () {
    countPageAcross($this);
    $queries = app(DiscrepancyQueries::class);
    app(EffectiveCapabilities::class)->for($this->owner->id);
    expect(DB::table('cost_attributions')->where('cost_class', 'LOSS')->count())->toBe(0)
        ->and($queries->findings($this->owner->id)['rows'])->toBe([])
        ->and(collect($queries->cases($this->owner->id)['rows'])->pluck('pending')->all())->toBe(['2']);
});
