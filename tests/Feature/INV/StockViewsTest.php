<?php

use App\Modules\CST\Queries\CostFigures;
use App\Modules\INV\Queries\MovementHistory;
use App\Modules\INV\Queries\StockViews;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;
use Tests\Support\StockWorld;

// TL-04 (TASK-040): ROUTE-018, ROUTE-019 and ROUTE-020's props per viewer; the acceptance criteria named on each. The pool is physical and cost stays with its family (PJ-01–PJ-04; PJ-22).

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
    $this->goods = $goods;
    $this->product = $goods['product'];
    $this->unit = $goods['base']->id;
    $this->rack = ReceivingWorld::rack();
    $this->other = InvoiceWorld::make()->company;
    $this->viewer = Authorization::admin([$this->company->id], ['stock.view']);
});

afterEach(fn () => @unlink($this->hotFile));

function deferred(object $test, object $user, string $url, string $component, string $props): array
{
    $version = (string) $test->actingAs($user)->get($url)->assertOk()->viewData('page')['version'];

    return $test->actingAs($user)->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => $component, 'X-Inertia-Partial-Data' => $props])->assertOk()->json('props');
}

it('shows the four quantities and the formula per product and per rack and condition: 10, 3, 2, 5 (annex 7; AC-22; OWN-04; D-IA-17; criterion 1)', function () {
    $project = $this->world->project(['WAREHOUSE'], '20');
    $demand = DB::table('project_lines')->where('project_id', $project->id)->first();
    $unit = (int) DB::table('product_units')->where('product_id', $demand->product_id)->where('is_base', true)->value('unit_id');
    $lot = StockWorld::lot($this->actor->id, $this->company, (int) $demand->product_id, $unit, $this->rack);
    StockWorld::conditionChange($this->actor->id, $this->company, (int) $demand->product_id, $lot, $this->rack, '2');
    expect(ReservationWorld::reserve($this->actor->id, $demand->id, '3'))->toBe('COMMITTED');
    $public = DB::table('products')->where('id', $demand->product_id)->value('public_id');

    $row = collect($this->actingAs($this->viewer)->get('/gudang/stok')->assertOk()->viewData('page')['props']['list']['rows'])->firstWhere('id', $public);
    expect($row['figures'])->toMatchArray(['physical' => '10', 'reserved' => '3', 'unusable' => '2', 'available' => '5']);

    $this->actingAs($this->viewer)->get("/gudang/stok/{$public}")->assertInertia(fn (AssertableInertia $page) => $page->component('INV/ProductStock', false)
        ->where('product.figures.available', '5')->where('product.figures.physical', '10')->where('places', [['rack' => DB::table('locations')->where('id', $this->rack)->value('code'), 'condition' => 'UNUSABLE', 'qty' => '2'], ['rack' => DB::table('locations')->where('id', $this->rack)->value('code'), 'condition' => 'USABLE', 'qty' => '8']])->etc());

    $rows = $this->actingAs($this->viewer)->get('/gudang/stok?tampil=rak')->viewData('page')['props']['list']['rows'];
    expect(collect($rows)->where('id', $public)->pluck('qty', 'condition')->all())->toBe(['UNUSABLE' => '2', 'USABLE' => '8']);
});

it('shows a pooled lot of another company by its code, quantity, rack and condition and the marker alone; a lot in scope its source, kind, date and acquired quantity (UXS-20; PJ-01; PJ-02; PJ-04; challenge 8c; criteria 2, 3)', function () {
    $own = StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '3');
    $otherActor = ReceivingWorld::actor($this->other, StockWorld::CAPABILITIES);
    StockWorld::lot($otherActor->id, $this->other, $this->product->id, $this->unit, $this->rack, '4');
    $props = deferred($this, $this->viewer, "/gudang/stok/{$this->product->public_id}", 'INV/ProductStock', 'group');
    $lots = collect($props['group']['lots']);
    $mine = $lots->firstWhere('other', false);
    $theirs = $lots->firstWhere('other', true);

    expect($mine)->toHaveKeys(['code', 'qty', 'rack', 'condition', 'company', 'kind', 'date', 'acquired'])->and($mine['code'])->toBe(DB::table('inventory_lots')->where('id', $own)->value('lot_code'))->and($mine['company'])->toContain($this->company->code)
        ->and(array_keys($theirs))->toEqualCanonicalizing(['id', 'code', 'qty', 'rack', 'condition', 'oldest', 'other'])
        ->and(json_encode($props))->not->toContain($this->other->code)->not->toContain((string) $this->other->legal_name);
});

it('carries no cost in any prop, partial reload or deferred group of the pooled views; lot cost only with cost.view of its source company (PJ-02; DP-15; SM-25; criterion 3)', function () {
    $own = StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '3');
    $otherActor = ReceivingWorld::actor($this->other, StockWorld::CAPABILITIES);
    $foreign = StockWorld::lot($otherActor->id, $this->other, $this->product->id, $this->unit, $this->rack, '4');
    $forbidden = '/"(cost|price|remaining_cost|unit_price|flagged|value|amount)"/';

    foreach (['group', 'costs', 'movements'] as $part) {
        $props = deferred($this, $this->viewer, "/gudang/stok/{$this->product->public_id}", 'INV/ProductStock', $part);
        expect(json_encode($props))->not->toMatch($forbidden);
    }

    expect(json_encode($this->actingAs($this->viewer)->get('/gudang/stok')->viewData('page')['props']))->not->toMatch($forbidden)
        ->and(deferred($this, $this->viewer, '/gudang/stok', 'INV/Stock', 'values')['values'])->toBe([]);

    $costs = deferred($this, $this->actor, "/gudang/stok/{$this->product->public_id}", 'INV/ProductStock', 'costs')['costs'];
    expect($costs)->toHaveCount(1)->and(array_values($costs)[0]['cost'])->toBe('3000.00');
    expect($own)->not->toBe($foreign);
});

it('values only its own companies\' lots per company, never a total across companies (PJ-22; CS-08; criterion 4)', function () {
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '3');
    $otherActor = ReceivingWorld::actor($this->other, StockWorld::CAPABILITIES);
    StockWorld::lot($otherActor->id, $this->other, $this->product->id, $this->unit, $this->rack, '4');

    $values = app(CostFigures::class)->stockValues($this->actor->id);
    expect($values)->toHaveCount(1)->and($values[0]['company'])->toBe($this->company->code)->and($values[0]['value'])->toBe('3000.00');

    $both = Authorization::admin([$this->company->id, $this->other->id], ['stock.view', 'cost.view', 'projects.view']);
    expect(array_column(app(CostFigures::class)->stockValues($both->id), 'value'))->toEqualCanonicalizing(['3000.00', '4000.00']);
});

it('shows a pending quantity as its own line on PG-018 and PG-019, a company\'s view only its own exposure (UXS-39; PJ-07; PJ-22; criterion 5)', function () {
    $lot = StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack);
    StockWorld::recognition($this->actor->id, $this->company, $this->product->id, $lot, $this->rack, '2', '10');

    $row = collect($this->actingAs($this->viewer)->get('/gudang/stok')->viewData('page')['props']['list']['rows'])->firstWhere('id', $this->product->public_id);
    expect($row['pending'])->toBe('2')->and($row['figures']['physical'])->toBe('8');
    $this->actingAs($this->viewer)->get("/gudang/stok/{$this->product->public_id}")->assertInertia(fn (AssertableInertia $page) => $page->where('product.pending', '2')->etc());

    $values = app(CostFigures::class)->stockValues($this->actor->id);
    expect($values[0]['exposures'])->toHaveCount(1)->and($values[0]['exposures'][0]['qty'])->toBe('10.000')->and(json_encode($values))->not->toContain('pending');
});

it('advises below the minimum over usable availability, the link only with purchase.manage, buying nothing (BR-INV-10; J-MD-05; AC-22; criterion 6)', function () {
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '3');
    DB::table('products')->where('id', $this->product->id)->update(['min_stock_qty_base' => '5']);
    $purchases = DB::table('purchases')->count();

    $row = collect($this->actingAs($this->viewer)->get('/gudang/stok')->viewData('page')['props']['list']['rows'])->firstWhere('id', $this->product->public_id);
    expect($row['below'])->toBe('5');
    $this->actingAs($this->viewer)->get('/gudang/stok')->assertInertia(fn (AssertableInertia $page) => $page->where('can.purchase', false)->etc());
    $this->actingAs($this->actor)->get('/gudang/stok')->assertInertia(fn (AssertableInertia $page) => $page->where('can.purchase', true)->etc());
    $this->actingAs($this->actor)->get("/pembelian/baru?produk={$this->product->public_id}")->assertOk();
    expect(DB::table('purchases')->count())->toBe($purchases);
});

it('itemizes a movement in scope and a pool movement, its project only with projects.view and purchase only with cost.view, another company\'s never, no cost (PJ-03; AC-06; criterion 7)', function () {
    $project = $this->world->project(['WAREHOUSE'], '20');
    $demand = DB::table('project_lines')->where('project_id', $project->id)->first();
    $unit = (int) DB::table('product_units')->where('product_id', $demand->product_id)->where('is_base', true)->value('unit_id');
    $purchase = ReceivingWorld::purchase($this->company, $demand->product_id, $unit, '5', '1000', $project->id, $demand->id);
    expect(ReceivingWorld::receive($this->actor->id, $this->company->id, $purchase->id, ReceivingWorld::line($purchase)->id, $unit, $this->rack, '5', 'SJ-7'))->toBe('COMMITTED');
    $lot = ReceivingWorld::lotOf(ReceivingWorld::line($purchase)->id);
    StockWorld::recognition($this->actor->id, $this->company, (int) $demand->product_id, $lot, $this->rack, '1', '5');
    $otherActor = ReceivingWorld::actor($this->other, StockWorld::CAPABILITIES);
    StockWorld::lot($otherActor->id, $this->other, $this->product->id, $this->unit, $this->rack, '4');

    $rows = collect($this->actingAs($this->viewer)->get('/gudang/riwayat-stok')->assertOk()->viewData('page')['props']['list']['rows']);
    expect($rows)->toHaveCount(2)->and($rows->pluck('type')->sort()->values()->all())->toBe(['RECEIPT', 'UNATTRIBUTED_RECOGNITION']);
    $receipt = $rows->firstWhere('type', 'RECEIPT');
    expect($receipt)->toMatchArray(['direction' => 'IN', 'qty' => '5', 'project' => null, 'purchase' => null, 'reference' => null])->and($receipt['lot'])->not->toBeNull()->and($receipt['rack'])->not->toBe('')->and($receipt['actor'])->not->toBeNull()
        ->and(json_encode($rows))->not->toMatch('/"(cost|price|amount)"/');

    $wide = Authorization::admin([$this->company->id], ['stock.view', 'projects.view', 'cost.view']);
    $row = collect($this->actingAs($wide)->get('/gudang/riwayat-stok')->viewData('page')['props']['list']['rows'])->firstWhere('type', 'RECEIPT');
    expect($row['project']['label'])->toBe($project->project_number)->and($row['purchase']['label'])->toBe($purchase->purchase_number)->and($row['reference'])->toBe('SJ-7');

    $none = app(MovementHistory::class)->page(Authorization::admin([$this->other->id], ['stock.view'])->id, ['produk' => $this->goods['product']->public_id]);
    expect($none['rows'])->toHaveCount(1)->and($none['rows'][0]['company'])->toBe($this->other->code);
});

it('marks the oldest lot "Ambil Lebih Dulu", nothing else depending on it (BR-INV-04; criterion 8)', function () {
    $first = StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    $lots = collect(deferred($this, $this->viewer, "/gudang/stok/{$this->product->public_id}", 'INV/ProductStock', 'group')['group']['lots']);

    expect($lots->where('oldest', true))->toHaveCount(1)->and($lots->firstWhere('oldest', true)['code'])->toBe(DB::table('inventory_lots')->where('id', $first)->value('lot_code'));
});

it('never shows a service on the stock views, a drop-ship having no warehouse event (BR-INV-11; AC-20; REF-030; criterion 9)', function () {
    $service = Projects::product('SERVICE');
    DB::table('stock_product_balances')->insertOrIgnore(['product_id' => $service['product']->id, 'recorded_at' => now()]);

    expect(collect(app(StockViews::class)->products(null, null)['rows'])->pluck('id')->all())->not->toContain($service['product']->public_id);
    $this->actingAs($this->viewer)->get("/gudang/stok/{$service['product']->public_id}")->assertNotFound();
});

it('keeps both records in the history, linked by "Dikoreksi oleh" and "Mengoreksi" (BR-CR-02; criterion 15)', function () {
    $lot = StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack);
    $change = StockWorld::conditionChange($this->actor->id, $this->company, $this->product->id, $lot, $this->rack, '2');
    $decrease = StockWorld::decrease($this->actor->id, $this->company, $this->product->id, $lot, $this->rack, '1');
    expect(StockWorld::reverse($this->actor->id, $this->company->id, $change))->toBe('COMMITTED')->and(StockWorld::reverse($this->actor->id, $this->company->id, $decrease))->toBe('COMMITTED');
    $rows = collect(app(MovementHistory::class)->page($this->actor->id, [])['rows']);
    $publicOf = fn (int $id) => DB::table('stock_movements')->where('id', $id)->value('public_id');
    $correcting = $rows->firstWhere('type', 'MOVEMENT_REVERSAL');
    $restoring = $rows->firstWhere('type', 'RESTORATION');

    expect($rows->firstWhere('movement', $publicOf($change))['corrected_by'])->toBe($correcting['movement'])->and($correcting['corrects'])->toBe($publicOf($change))
        ->and($rows->firstWhere('movement', $publicOf($decrease))['corrected_by'])->toBe($restoring['movement'])->and($restoring['corrects'])->toBe($publicOf($decrease))
        ->and($rows->firstWhere('movement', $publicOf($change))['reversible'])->toBeFalse();
});

it('offers "Balikkan" only on a reversible movement, "Balikkan Penerimaan" on a receipt, the receipt\'s figures on request (CM-28; ROUTE-183; IP-20)', function () {
    $lot = StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack);
    $change = StockWorld::conditionChange($this->actor->id, $this->company, $this->product->id, $lot, $this->rack, '2');
    $rows = collect(app(MovementHistory::class)->page($this->actor->id, [])['rows']);
    $receipt = $rows->firstWhere('type', 'RECEIPT');

    expect($receipt['reversible'])->toBeFalse()->and($receipt['receipt'])->not->toBeNull()
        ->and($rows->where('movement', DB::table('stock_movements')->where('id', $change)->value('public_id'))->where('reversible', true))->toHaveCount(1);

    $props = deferred($this, $this->actor, "/gudang/riwayat-stok?terima={$receipt['receipt']}", 'INV/StockHistory', 'reversal');
    expect($props['reversal']['handle'])->toBe($receipt['receipt'])->and($props['reversal']['places'])->toHaveCount(2);
});

it('holds the physical query classes free of any cost table (PJ-01; SM-03; DATABASE §5.1)', function () {
    foreach (['StockViews', 'MovementHistory', 'ScanLookup'] as $class) {
        expect(file_get_contents(app_path("Modules/INV/Queries/{$class}.php")))->not->toMatch('/lot_cost_|cost_attributions|purchase_charge|unit_price|line_value/');
    }
});

it('keeps the commands PG-019 hosts absent until their routes exist (RC-06; IG-01; criterion 17)', function () {
    expect(Route::has('inv.stock.move'))->toBeFalse();
    $source = file_get_contents(resource_path('js/Pages/INV/ProductStock.tsx'));
    expect($source)->not->toContain('Pindah Lokasi')->not->toContain('/gudang/stok-opname');
});
