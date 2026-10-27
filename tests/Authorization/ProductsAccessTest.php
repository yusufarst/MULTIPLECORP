<?php

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 and TL-14 (TASK-027; PJ-14; RD-02; CS-05; CS-06; SM-01; SM-25; criterion 6): the catalogue's S0 identity to
// every active account; purchase defaults only with `cost.view`, selling and SPJ-reference only with `finance.view`,
// stock figures only with `stock.view`, each absent otherwise; every command `products.edit`, a price default's change
// also its kind's family, refused without and writing nothing.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->product = Product::factory()->create(['name' => 'Spidol Papan']);
    DB::table('stock_product_balances')->insert(['product_id' => $this->product->id, 'on_hand_qty' => 12]);
    foreach (['PURCHASE_DEFAULT' => 4000, 'SELLING_DEFAULT' => 6000, 'SPJ_REFERENCE' => 7000] as $kind => $amount) {
        DB::table('product_price_history')->insert(['product_id' => $this->product->id, 'price_kind' => $kind, 'amount' => $amount, 'effective_from' => '2026-10-01']);
    }
});

afterEach(fn () => @unlink($this->hotFile));

/** PG-056's record of the product as a viewer of the given capabilities receives it. */
function productPage(object $test, array $capabilities, Closure $assert): void
{
    $test->actingAs(Authorization::admin([$test->company->id], $capabilities))->get("/data-master/produk?produk={$test->product->public_id}")->assertOk()->assertInertia($assert);
}

it('shows every active account the product and nothing of a family it lacks (criterion 6; RD-01)', function () {
    productPage($this, [], fn (AssertableInertia $page) => $page->where('product.name', 'Spidol Papan')->where('product.prices', [])->missing('product.stock')->where('can.edit', false));
});

it('shows each price kind only to its family and stock only with stock.view (criterion 6; PJ-14; SM-25)', function () {
    productPage($this, ['cost.view'], fn (AssertableInertia $page) => $page
        ->where('product.prices', [['kind' => 'PURCHASE_DEFAULT', 'history' => [['amount' => '4000.00', 'date' => '2026-10-01', 'reason' => null]]]])->missing('product.stock'));
    productPage($this, ['finance.view'], fn (AssertableInertia $page) => $page->where('product.prices.0.kind', 'SELLING_DEFAULT')->where('product.prices.1.kind', 'SPJ_REFERENCE')->has('product.prices', 2));
    productPage($this, ['stock.view'], fn (AssertableInertia $page) => $page->where('product.prices', [])->where('product.stock.on_hand', '12.000')->where('product.stock.available', '12.000'));
});

it('refuses every command without products.edit and writes nothing (TL-06; ROUTE-145–ROUTE-147)', function (string $method, string $path, array $body) {
    $viewer = Authorization::admin([$this->company->id], ['cost.view', 'finance.view', 'stock.view']);
    $before = [DB::table('products')->count(), DB::table('product_barcodes')->count(), DB::table('product_price_history')->count(), DB::table('command_log')->count()];

    $this->actingAs($viewer)->call($method, str_replace('{id}', $this->product->public_id, $path), ['command_id' => (string) Str::uuid(), ...$body])->assertForbidden();
    expect([DB::table('products')->count(), DB::table('product_barcodes')->count(), DB::table('product_price_history')->count(), DB::table('command_log')->count()])->toBe($before);
})->with([
    ['POST', '/data-master/produk', ['kind' => 'GOODS', 'name' => 'X', 'unit_code' => 'PCS', 'quantity_scale' => 0]],
    ['PUT', '/data-master/produk/{id}', ['action' => 'archive', 'lock_version' => 0]],
    ['POST', '/data-master/produk/{id}/barcode', ['kind' => 'INTERNAL']],
]);

it('refuses a price default\'s change without its kind\'s family, re-read in the command (PJ-14; CI-05)', function () {
    $editor = Authorization::admin([$this->company->id], ['products.edit', 'cost.view']);

    $this->actingAs($editor)->put("/data-master/produk/{$this->product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'price', 'price_kind' => 'SELLING_DEFAULT', 'amount' => '1'])->assertForbidden();
    $this->actingAs($editor)->put("/data-master/produk/{$this->product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'price', 'price_kind' => 'PURCHASE_DEFAULT', 'amount' => '4500'])->assertRedirect();

    expect(DB::table('product_price_history')->where('product_id', $this->product->id)->count())->toBe(4)
        ->and(DB::table('product_price_history')->where('price_kind', 'SELLING_DEFAULT')->count())->toBe(1);
    $this->actingAs($editor)->get('/data-master/produk')->assertInertia(fn (AssertableInertia $page) => $page->where('can', ['edit' => true, 'prices' => ['PURCHASE_DEFAULT']]));
});
