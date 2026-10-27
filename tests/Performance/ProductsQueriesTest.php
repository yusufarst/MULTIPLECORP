<?php

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-027; PERFORMANCE_TARGETS PFM-01, PFM-02, PFM-20; QB-03; QB-04): PG-056's list with its thumbnails and a
// product's record — units, barcodes, prices with their history, images, stock — in a number of statements that never
// depends on the rows shown; a creation within an NX-01 command's budget.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->admin = Authorization::admin([$this->company->id], ['products.edit', 'cost.view', 'finance.view', 'stock.view']);
});

afterEach(fn () => @unlink($this->hotFile));

function catalogStatements(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('renders PG-056\'s list and a product in the same number of statements for few rows and many (PFM-01; PFM-02)', function () {
    $product = Product::factory()->create();
    $page = fn (string $path) => catalogStatements(fn () => $this->actingAs($this->admin)->get($path)->assertOk());
    $detail = "/data-master/produk?produk={$product->public_id}";
    $page('/data-master/produk');
    [$list, $one] = [$page('/data-master/produk'), $page($detail)];

    Product::factory()->count(30)->create();
    foreach (range(1, 10) as $number) {
        DB::table('product_price_history')->insert(['product_id' => $product->id, 'price_kind' => 'SELLING_DEFAULT', 'amount' => $number, 'effective_from' => '2026-10-01']);
        DB::table('product_barcodes')->insert(['product_id' => $product->id, 'code' => "PERF{$number}", 'kind' => 'MANUFACTURER']);
    }

    expect($page('/data-master/produk'))->toBe($list)->and($page($detail))->toBe($one)->and($one)->toBeLessThanOrEqual(40);
});

it('runs a product\'s creation within an NX-01 command\'s budget (PFM-20)', function () {
    $count = catalogStatements(fn () => $this->actingAs($this->admin)->post('/data-master/produk', ['command_id' => (string) Str::uuid(), 'kind' => 'GOODS',
        'name' => 'Produk Anggaran', 'unit_code' => 'PCS', 'quantity_scale' => 0])->assertRedirect());

    expect($count)->toBeLessThanOrEqual(45);
});
