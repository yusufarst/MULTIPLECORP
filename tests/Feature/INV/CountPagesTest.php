<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\StockCountFinding;
use App\Modules\INV\Queries\CountQueries;
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

it('shows a resolution\'s reason and decider on PG-020 only to the Owner and a cost.view holder of the company it charged (PJ-07; rows 71, 72; ER-06 point 5)', function () {
    $other = countPageAcross($this);
    $case = (int) DB::table('unattributed_loss_cases')->value('id');
    expect(CountWorld::resolve($this->owner->id, $case, [['company' => $this->foreign->company->id, 'closes' => [['lot' => $other, 'qty' => '2']]]]))->toBe('COMMITTED');
    $row = fn (object $viewer): ?array => collect($this->actingAs($viewer)->get('/gudang/riwayat-stok')->assertOk()->viewData('page')['props']['list']['rows'])->firstWhere('type', 'UNATTRIBUTED_RESOLUTION');
    $both = Authorization::admin([$this->company->id, $this->foreign->company->id], ['stock.view']);
    $charged = Authorization::admin([$this->foreign->company->id], ['stock.view', 'cost.view']);

    expect($row($both))->not->toBeNull()->and($row($both)['reason'])->toBeNull()->and($row($both)['actor'])->toBeNull()
        ->and($row($charged)['reason'])->toBe('Keputusan Owner atas selisih')->and($row($charged)['actor'])->not->toBeNull()
        ->and($row($this->owner)['reason'])->toBe('Keputusan Owner atas selisih');
});

it('reverses an identified loss first when the Owner attributes a finding through ROUTE-197, the rest one flagged lot (AX-07; ER-06 point 8)', function () {
    $count = CountWorld::open($this->applier->id, $this->product, $this->rack);
    expect(CountWorld::line($this->applier->id, $count, $this->product, $this->rack, '8'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->applier->id, CountWorld::entry($count, [AdjustmentWorld::pick($this->lot, $this->rack, '2')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $consumption = (int) DB::table('stock_consumptions')->where('kind', 'LOSS')->value('id');
    $surplus = CountWorld::open($this->applier->id, $this->product, $this->rack);
    expect(CountWorld::line($this->applier->id, $surplus, $this->product, $this->rack, '10'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->applier->id, CountWorld::entry($surplus)))->toBe('COMMITTED');
    $finding = countPageProps($this, $this->owner)['findings']['rows'][0];
    $handle = RowHandle::of('stock_consumptions', $consumption);
    expect(collect($finding['losses'])->pluck('id')->all())->toContain($handle);

    $this->actingAs($this->owner)->post("/gudang/selisih-temuan/{$finding['id']}/surplus", [
        'command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'dasar_biaya' => '10000', 'kebijakan_seri' => false, 'kebijakan_skala' => 0,
        'pulihkan' => [['konsumsi' => $handle, 'jumlah' => '1']], 'alasan' => 'Satu unit ditemukan kembali', 'tanggal' => BusinessDate::today(),
    ])->assertRedirect('/gudang/selisih-temuan')->assertSessionHas('inertia.flash_data.answer.kind', 'COMMITTED');

    expect((string) DB::table('stock_consumption_balances')->where('stock_consumption_id', $consumption)->value('restored_qty_base'))->toBe('1.000')
        ->and((string) DB::table('inventory_lots')->where('acquisition_kind', 'SURPLUS')->value('acquired_qty_base'))->toBe('1.000')
        ->and(DB::table('stock_count_findings')->where('state', 'ATTRIBUTED')->count())->toBe(1)
        ->and((string) DB::table('stock_product_balances')->where('product_id', $this->product)->value('on_hand_qty'))->toBe('10.000');
});

it('recounts a stale registration count as a registration count (ROUTE-191; `DIR-101` F2; ER-06 point 14)', function () {
    $registrar = Authorization::admin([$this->company->id], [...CountWorld::CAPABILITIES, 'products.edit']);
    expect(CountWorld::openRegistration($registrar->id, $this->product))->toBe('COMMITTED');
    $count = (int) DB::table('stock_counts')->orderByDesc('id')->value('id');
    // A receipt moves the scope after the registration count started.
    StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->unit, $this->rack, '1', '1000');
    expect(CountWorld::apply($registrar->id, CountWorld::entry($count)))->toContain('ST-03');
    $public = (string) DB::table('stock_counts')->where('id', $count)->value('public_id');
    $page = $this->actingAs($registrar)->get("/gudang/stok-opname?hitungan={$public}")->assertOk()->viewData('page')['props']['count'];
    expect($page['state'])->toBe('STALE')->and($page['purpose'])->toBe('SERIAL_REGISTRATION')
        ->and(file_get_contents(resource_path('js/Pages/INV/StockCounts.tsx')))->toContain("count.purpose === 'SERIAL_REGISTRATION' ? { produk: count.product.id, registrasi: true }");

    // What "Hitung Ulang" sends for it opens a registration count again.
    $this->actingAs($registrar)->post('/gudang/stok-opname', ['command_id' => (string) Str::uuid(), 'produk' => $this->productId, 'registrasi' => true])->assertRedirect();
    expect(DB::table('stock_counts')->orderByDesc('id')->first(['purpose', 'state', 'location_id']))->toMatchObject(['purpose' => 'SERIAL_REGISTRATION', 'state' => 'OPEN', 'location_id' => null]);
});

it('pages PG-029\'s findings by cursor, says a tampered cursor failed, and searches the proofs (QB-05; IP-18; ER-06 point 16)', function () {
    foreach (range(1, DiscrepancyQueries::PAGE + 1) as $number) {
        StockCountFinding::factory()->create(['product_id' => $this->product]);
    }

    $first = countPageProps($this, $this->owner)['findings'];
    expect($first['rows'])->toHaveCount(DiscrepancyQueries::PAGE)->and($first['more'])->toBeString()->and($first['cursor_failed'])->toBeFalse();
    $next = countPageProps($this, $this->owner, '/gudang/selisih-temuan?setelah_temuan='.urlencode($first['more']))['findings'];
    expect($next['rows'])->toHaveCount(1)->and($next['more'])->toBeNull()
        ->and(collect($first['rows'])->pluck('id')->intersect(collect($next['rows'])->pluck('id'))->all())->toBe([])
        ->and(countPageProps($this, $this->owner, '/gudang/selisih-temuan?setelah_temuan=rusak')['findings'])->toMatchArray(['rows' => [], 'cursor_failed' => true]);

    $issuer = (string) DB::table('evidence_documents')->where('id', $this->evidence)->value('declared_issuer');
    $proofs = countPageProps($this, $this->owner, '/gudang/selisih-temuan?cari_bukti='.urlencode($issuer))['proofs'];
    expect($proofs['find'])->toBe($issuer)->and($proofs['rows'])->not->toBe([])
        ->and(countPageProps($this, $this->owner, '/gudang/selisih-temuan?cari_bukti=tidak-ada-bukti')['proofs']['rows'])->toBe([]);
});

it('resolves an old finding\'s handle however many findings follow it; a lot only among the product\'s (PG-029; ROUTE-197)', function () {
    $old = (int) StockCountFinding::factory()->create(['product_id' => $this->product])->id;
    $row = (array) DB::table('stock_count_findings')->where('id', $old)->first();
    unset($row['id']);

    foreach (array_chunk(array_fill(0, 5000, [...$row, 'state' => 'WITHDRAWN']), 500) as $chunk) {
        DB::table('stock_count_findings')->insert($chunk);
    }

    $queries = app(DiscrepancyQueries::class);
    expect($queries->finding(RowHandle::of('stock_count_findings', $old)))->toBe(['id' => $old, 'product' => (int) $this->product])
        ->and($queries->lot((int) $this->product, RowHandle::of('inventory_lots', $this->lot)))->toBe($this->lot)
        ->and($queries->lot((int) $this->product + 1000, RowHandle::of('inventory_lots', $this->lot)))->toBeNull();
});

it('offers a case\'s lots of the viewer\'s companies before the bound, and finds the rest by lot code (PJ-07; OD-10; QB-05)', function () {
    $other = countPageAcross($this);
    // Older lots of the first company fill the first page.
    foreach (InventoryLot::factory()->count(DiscrepancyQueries::PAGE)->create(['product_id' => $this->product, 'source_company_id' => $this->company->id]) as $filler) {
        DB::table('stock_lot_balances')->insert(['lot_id' => $filler->id, 'product_id' => $this->product, 'location_id' => $this->rack, 'condition' => 'USABLE', 'qty' => '1']);
    }

    $resolver = Authorization::admin([$this->foreign->company->id], ['stock.view', 'evidence.view', 'stock.resolve_unattributed_evidence']);
    $case = countPageProps($this, $resolver)['cases']['rows'][0];
    expect(collect($case['lots'])->pluck('lot')->all())->toBe([RowHandle::of('inventory_lots', $other)])->and($case['lots_more'])->toBeFalse();

    $all = countPageProps($this, $this->owner)['cases']['rows'][0];
    $code = (string) DB::table('inventory_lots')->where('id', $other)->value('lot_code');
    $found = countPageProps($this, $this->owner, "/gudang/selisih-temuan?kasus_lot={$all['id']}&cari_lot=".urlencode($code))['cases']['rows'][0];
    expect($all['lots'])->toHaveCount(DiscrepancyQueries::PAGE)->and($all['lots_more'])->toBeTrue()
        ->and(collect($found['lots'])->pluck('lot')->all())->toBe([RowHandle::of('inventory_lots', $other)])->and($found['lots_find'])->toBe($code);
});

it('refuses a restoration through ROUTE-197 of a loss at another rack than the finding\'s (AX-07; L-31; C-14)', function () {
    $elsewhere = ReceivingWorld::rack();
    $lot = StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->unit, $elsewhere, '5', '10000');
    $count = CountWorld::open($this->applier->id, $this->product, $elsewhere);
    expect(CountWorld::line($this->applier->id, $count, $this->product, $elsewhere, '4'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->applier->id, CountWorld::entry($count, [AdjustmentWorld::pick($lot, $elsewhere, '1')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $consumption = (int) DB::table('stock_consumptions')->where('kind', 'LOSS')->value('id');
    $surplus = CountWorld::open($this->applier->id, $this->product, $this->rack);
    expect(CountWorld::line($this->applier->id, $surplus, $this->product, $this->rack, '11'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->applier->id, CountWorld::entry($surplus)))->toBe('COMMITTED');
    $finding = (int) DB::table('stock_count_findings')->where('state', 'OPEN')->value('id');

    expect(CountWorld::attribute($this->owner->id, $finding, $this->company->id, '10000', [['consumption' => $consumption, 'qty' => '1']]))->toContain('C-14')
        ->and((string) DB::table('stock_consumption_balances')->where('stock_consumption_id', $consumption)->value('restored_qty_base'))->toBe('0.000')
        ->and(DB::table('stock_count_findings')->where('id', $finding)->value('state'))->toBe('OPEN');
});

it('resolves an open loss\'s handle for ROUTE-193 however many later losses the product has (AZ-08; L-31)', function () {
    $count = CountWorld::open($this->applier->id, $this->product, $this->rack);
    expect(CountWorld::line($this->applier->id, $count, $this->product, $this->rack, '9'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->applier->id, CountWorld::entry($count, [AdjustmentWorld::pick($this->lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $open = (int) DB::table('stock_consumptions')->where('kind', 'LOSS')->value('id');
    $row = (array) DB::table('stock_consumptions')->where('id', $open)->first();
    unset($row['id']);
    // Later losses of the product, none of them open.
    DB::table('stock_consumptions')->insert(array_fill(0, 500, [...$row, 'command_id' => null, 'command_line_no' => null]));

    expect(app(CountQueries::class)->consumption($this->applier->id, $this->product, RowHandle::of('stock_consumptions', $open)))->toBe($open);
});

it('gives a serialized count its whole missing total for the cut, apart from the bounded list (SF-RSV-CUT; QB-05)', function () {
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $serial = (array) DB::table('serial_units')->where('id', StockWorld::serial($this->product, $this->lot, $this->rack))->first();
    unset($serial['id']);
    DB::table('serial_units')->insert(array_map(fn (int $n): array => [...$serial, 'serial_number' => "SN-BATAS-{$n}", 'serial_key' => "sn-batas-{$n}", 'command_id' => null, 'command_line_no' => null], range(1, CountQueries::LINES)));
    $count = CountWorld::open($this->applier->id, $this->product, $this->rack);
    $public = (string) DB::table('stock_counts')->where('id', $count)->value('public_id');
    $page = $this->actingAs($this->applier)->get("/gudang/stok-opname?hitungan={$public}")->assertOk()->viewData('page')['props']['count'];

    expect($page['missing'])->toHaveCount(CountQueries::LINES)->and($page['missing_total'])->toBe(CountQueries::LINES + 1)
        ->and(file_get_contents(resource_path('js/Pages/INV/StockCounts.tsx')))->toContain('count.missing_total - count.returning');
});

it('reaches every lot holding the product in a registration count, by lot code past the bound (`DIR-101` F2; QB-05)', function () {
    $registrar = Authorization::admin([$this->company->id], [...CountWorld::CAPABILITIES, 'products.edit']);
    $last = null;

    foreach (InventoryLot::factory()->count(CountQueries::LINES)->create(['product_id' => $this->product, 'source_company_id' => $this->company->id]) as $filler) {
        DB::table('stock_lot_balances')->insert(['lot_id' => $filler->id, 'product_id' => $this->product, 'location_id' => $this->rack, 'condition' => 'USABLE', 'qty' => '1']);
        $last = $filler;
    }

    expect(CountWorld::openRegistration($registrar->id, $this->product))->toBe('COMMITTED');
    $public = (string) DB::table('stock_counts')->orderByDesc('id')->value('public_id');
    $count = fn (string $query = ''): array => $this->actingAs($registrar)->get("/gudang/stok-opname?hitungan={$public}{$query}")->assertOk()->viewData('page')['props']['count'];
    $code = (string) $last?->lot_code;

    expect($count()['places'])->toHaveCount(CountQueries::LINES)->and($count()['places_more'])->toBeTrue()
        ->and(collect($count()['places'])->pluck('code')->all())->not->toContain($code)
        ->and(collect($count('&cari_lot='.urlencode($code))['places'])->pluck('code')->all())->toBe([$code])
        ->and(file_get_contents(resource_path('js/Pages/INV/StockCounts.tsx')))->toContain('data: { cari_lot: text }');
});

it('takes a split past one page\'s list in one application, up to its bound (SF-LOSS; QB-05)', function () {
    $public = (string) DB::table('stock_counts')->where('id', CountWorld::open($this->applier->id, $this->product, $this->rack))->value('public_id');
    $body = fn (int $lots): array => ['command_id' => (string) Str::uuid(), 'unit' => array_fill(0, $lots, ['lot' => RowHandle::of('inventory_lots', $this->lot), 'jumlah' => '1']), 'alasan' => 'Selisih', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()];

    $this->actingAs($this->applier)->post("/gudang/stok-opname/{$public}/terapkan", $body(CountQueries::LINES + 1))->assertSessionDoesntHaveErrors('unit');
    $this->actingAs($this->applier)->post("/gudang/stok-opname/{$public}/terapkan", $body(CountQueries::PICKS + 1))->assertSessionHasErrors('unit');
});

it('offers a retry at PG-029\'s case and resolution rows when an outcome is unknown, as at its findings (IP-02)', function () {
    $source = file_get_contents(resource_path('js/Pages/INV/Discrepancies.tsx'));

    expect($source)->toContain('onClick={owner.unknown ? owner.retry : proof.retry}')->toContain('onClick={reverse.retry}')->toContain('onClick={command.retry}');
});

it('reaches every lot of an ordinary count\'s scope by lot code past the bound, and keeps a named lot across searches (WF-INV-05; QB-05)', function () {
    $last = null;

    foreach (InventoryLot::factory()->count(CountQueries::LINES)->create(['product_id' => $this->product, 'source_company_id' => $this->company->id]) as $filler) {
        DB::table('stock_lot_balances')->insert(['lot_id' => $filler->id, 'product_id' => $this->product, 'location_id' => $this->rack, 'condition' => 'USABLE', 'qty' => '1']);
        $last = $filler;
    }

    $public = (string) DB::table('stock_counts')->where('id', CountWorld::open($this->applier->id, $this->product, $this->rack))->value('public_id');
    $count = fn (string $query = ''): array => $this->actingAs($this->applier)->get("/gudang/stok-opname?hitungan={$public}{$query}")->assertOk()->viewData('page')['props']['count'];
    $code = (string) DB::table('inventory_lots')->where('id', $this->lot)->value('lot_code');

    // The fillers are older: the fixture's own lot is the one past the bound.
    expect($count()['places'])->toHaveCount(CountQueries::LINES)->and($count()['places_more'])->toBeTrue()
        ->and(collect($count()['places'])->pluck('code')->all())->not->toContain($code)
        ->and(collect($count('&cari_lot='.urlencode($code))['places'])->pluck('code')->all())->toBe([$code])
        ->and(file_get_contents(resource_path('js/Pages/INV/StockCounts.tsx')))->toContain('setKept({ ...kept, [key]: place })');
});

it('offsets a serialized count\'s cut by the lost serials it brings back, as the application does (SF-RSV-CUT; L-48)', function () {
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $keys = array_map(fn (int $serial): string => (string) DB::table('serial_units')->where('id', $serial)->value('serial_key'), [StockWorld::serial($this->product, $this->lot, $this->rack), StockWorld::serial($this->product, $this->lot, $this->rack)]);
    $first = CountWorld::open($this->applier->id, $this->product, $this->rack);
    expect(CountWorld::line($this->applier->id, $first, $this->product, $this->rack, '1', $keys[0]))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->applier->id, CountWorld::entry($first, evidence: [$this->evidence])))->toBe('COMMITTED');

    $second = CountWorld::open($this->applier->id, $this->product, $this->rack);
    expect(CountWorld::line($this->applier->id, $second, $this->product, $this->rack, '1', $keys[1]))->toBe('COMMITTED');
    $public = (string) DB::table('stock_counts')->where('id', $second)->value('public_id');
    $count = $this->actingAs($this->applier)->get("/gudang/stok-opname?hitungan={$public}")->assertOk()->viewData('page')['props']['count'];

    expect($count['missing_total'])->toBe(1)->and($count['returning'])->toBe(1)
        ->and(file_get_contents(resource_path('js/Pages/INV/StockCounts.tsx')))->toContain('Math.max(0, count.missing_total - count.returning)');
});
