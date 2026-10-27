<?php

use App\Modules\CAT\Models\Product;
use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Services\Uploads;
use App\Modules\ORG\Models\Company;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Files;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-027; ROUTE-056, ROUTE-145–ROUTE-147; criteria 1, 2, 3, 5, 7, 9): a product or service created with its
// base unit and guard, an SKU another holds refused at its field; an alternate unit as an integer multiple; a
// manufacturer code normalized, one on another product refused, an internal one from the sequence; a price default
// forward with its history, saved purchase lines untouched; an image of the actor's own upload; an archive that keeps
// the product and offers it no more.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Storage::fake('files');
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->admin = Authorization::admin([$this->company->id], ['products.edit', 'cost.view', 'finance.view', 'stock.view']);
});

afterEach(fn () => @unlink($this->hotFile));

function productAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** A product made through ROUTE-145, as the page creates one. */
function createdProduct(object $test, array $fields = []): Product
{
    $test->actingAs($test->admin)->post('/data-master/produk', ['command_id' => (string) Str::uuid(), 'kind' => 'GOODS', 'name' => 'Kertas HVS A4',
        'sku' => 'KRT-'.Str::random(6), 'unit_code' => 'rim', 'unit_name' => 'Rim', 'quantity_scale' => 0, ...$fields])->assertRedirect();

    return Product::query()->latest('id')->firstOrFail()->refresh();
}

it('creates a product with its base unit and its guard, an SKU another holds refused at its field (ROUTE-145; criterion 8)', function () {
    $product = createdProduct($this, ['sku' => 'KRT-A4']);

    expect(productAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DB::table('units')->where('code', 'RIM')->value('name'))->toBe('Rim')
        ->and(DB::table('product_units')->where('product_id', $product->id)->where('is_base', true)->value('ratio_to_base'))->toBe(1)
        ->and(DB::table('stock_product_balances')->where('product_id', $product->id)->exists())->toBeTrue()
        ->and(DB::table('audit_events')->where('action', 'CAT.ProductCreated')->value('authority_class'))->toBe('ADM');

    $this->actingAs($this->admin)->post('/data-master/produk', ['command_id' => (string) Str::uuid(), 'kind' => 'GOODS', 'name' => 'Lain', 'sku' => 'krt-a4',
        'unit_code' => 'PCS', 'quantity_scale' => 0])->assertSessionHasErrors('sku');
    $this->actingAs($this->admin)->post('/data-master/produk', ['command_id' => (string) Str::uuid(), 'kind' => 'GOODS', 'name' => '', 'unit_code' => '?', 'quantity_scale' => 9])
        ->assertSessionHasErrors(['name', 'unit_code', 'quantity_scale']);
    expect(Product::query()->count())->toBe(1);
});

it('creates a service counted in whole units, never serialized (criterion 9; DATABASE §4.4)', function () {
    $service = createdProduct($this, ['kind' => 'SERVICE', 'name' => 'Jasa Pemasangan', 'unit_code' => 'JOB', 'quantity_scale' => 2]);
    expect($service->quantity_scale)->toBe(0);

    $this->actingAs($this->admin)->put("/data-master/produk/{$service->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'policy', 'lock_version' => 0, 'serialized' => true, 'quantity_scale' => 0])->assertRedirect();
    expect(productAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'BR-INV-11'])->and($service->refresh()->is_serialized)->toBeFalse();
});

it('adds an alternate unit as an integer multiple of the base, never a fraction (criterion 3; C-09; L-31)', function () {
    $product = createdProduct($this);
    $add = fn (array $body) => $this->actingAs($this->admin)->put("/data-master/produk/{$product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'unit', ...$body]);

    $add(['unit_code' => 'BOX', 'unit_name' => 'Box', 'ratio' => 5])->assertRedirect();
    expect(productAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DB::table('product_units')->join('units', 'units.id', '=', 'product_units.unit_id')->where('product_id', $product->id)->where('units.code', 'BOX')->value('ratio_to_base'))->toBe(5);

    $add(['unit_code' => 'BOX', 'ratio' => 10])->assertSessionHasErrors('unit_code');
    $add(['unit_code' => 'PAK', 'ratio' => '2.5'])->assertSessionHasErrors('ratio');
    $add(['unit_code' => 'PAK', 'ratio' => 1])->assertSessionHasErrors('ratio');
});

it('corrects an alternate unit\'s ratio at the version read, saved lines keeping the ratio they pinned (numeric annex 11; ST-01)', function () {
    $product = createdProduct($this);
    $this->actingAs($this->admin)->put("/data-master/produk/{$product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'unit', 'unit_code' => 'BOX', 'ratio' => 10])->assertRedirect();
    $line = PurchaseLine::factory()->for(Purchase::factory()->for($this->company))->for($product)->create(['ratio_to_base' => 10, 'entered_qty' => '2.000', 'qty_base' => '20.000']);
    $change = fn (int $version, int $ratio) => $this->actingAs($this->admin)->put("/data-master/produk/{$product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'ratio', 'unit_code' => 'box', 'ratio' => $ratio, 'lock_version' => $version]);

    $change(0, 12)->assertRedirect();
    expect(productAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DB::table('product_units')->join('units', 'units.id', '=', 'product_units.unit_id')->where('product_id', $product->id)->where('units.code', 'BOX')->value('ratio_to_base'))->toBe(12)
        ->and(DB::table('purchase_lines')->where('id', $line->id)->value('qty_base'))->toBe('20.000');

    $change(0, 6)->assertRedirect();
    expect(productAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'cause' => 'stale']);
    $this->actingAs($this->admin)->put("/data-master/produk/{$product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'ratio', 'unit_code' => 'RIM', 'ratio' => 3, 'lock_version' => 0])->assertNotFound();
});

it('makes another image the primary and moves an image in the order (DATABASE §4.4; DIR-089 (4))', function () {
    $product = createdProduct($this);
    $files = array_map(fn (string $name) => app(Uploads::class)->accept(Files::path(Files::png()), $name, FilePurpose::ProductImage, null, $this->admin->id)->public_id, ['a.png', 'b.png']);
    foreach ($files as $file) {
        $this->actingAs($this->admin)->put("/data-master/produk/{$product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'image', 'file' => $file])->assertRedirect();
    }
    $arrange = fn (string $file, string $move) => $this->actingAs($this->admin)->put("/data-master/produk/{$product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'arrange', 'file' => $file, 'move' => $move]);

    $arrange($files[1], 'primary')->assertRedirect();
    $arrange($files[1], 'earlier')->assertRedirect();
    $this->actingAs($this->admin)->get("/data-master/produk?produk={$product->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('product.images', [['file' => $files[1], 'primary' => true], ['file' => $files[0], 'primary' => false]]));

    $arrange($files[1], 'earlier')->assertRedirect();
    expect(productAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'WF-MD-04'])
        ->and(DB::table('product_images')->where('product_id', $product->id)->where('is_primary', true)->count())->toBe(1);
});

it('registers a manufacturer code by its key, refuses it on another product, and creates an internal one from the sequence (criteria 1, 2; BR-INV-09)', function () {
    $first = createdProduct($this);
    $second = createdProduct($this, ['name' => 'Map Plastik']);
    $register = fn (Product $product, array $body) => $this->actingAs($this->admin)->post("/data-master/produk/{$product->public_id}/barcode", ['command_id' => (string) Str::uuid(), ...$body]);

    $register($first, ['kind' => 'MANUFACTURER', 'code' => '0089 9123 4567'])->assertRedirect();
    expect(DB::table('product_barcodes')->where('product_id', $first->id)->value('code_key'))->toBe('008991234567');

    $register($second, ['kind' => 'MANUFACTURER', 'code' => '008991234567'])->assertSessionHasErrors(['code' => 'Barcode ini sudah terdaftar pada produk lain.']);
    $register($first, ['kind' => 'MANUFACTURER', 'code' => '00 89 91 23 45 67'])->assertSessionHasErrors(['code' => 'Barcode ini sudah terdaftar pada produk ini.']);

    $register($second, ['kind' => 'INTERNAL'])->assertRedirect();
    $register($second, ['kind' => 'INTERNAL'])->assertRedirect();
    $internal = DB::table('product_barcodes')->where('product_id', $second->id)->where('kind', 'INTERNAL')->orderBy('id')->pluck('code')->all();
    expect($internal)->toHaveCount(2)->and($internal[0])->toMatch('/^MC\d{8}$/')->and($internal[0])->not->toBe($internal[1]);

    $this->actingAs($this->admin)->get("/data-master/produk?produk={$second->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->component('CAT/Products', false)->where('product.barcodes.0.code', $internal[0]));
});

it('changes a price default forward only, its history listed and saved purchase lines untouched (criterion 5; BR-SN-01; REF-015)', function () {
    $product = createdProduct($this);
    $line = PurchaseLine::factory()->for(Purchase::factory()->for($this->company))->for($product)->create(['unit_price' => '45000.00']);
    $price = fn (string $kind, string $amount) => $this->actingAs($this->admin)->put("/data-master/produk/{$product->public_id}", ['command_id' => (string) Str::uuid(),
        'action' => 'price', 'price_kind' => $kind, 'amount' => $amount, 'reason' => 'Harga baru']);

    $price('PURCHASE_DEFAULT', '50000')->assertRedirect();
    $price('PURCHASE_DEFAULT', '52000.50')->assertRedirect();
    $price('SELLING_DEFAULT', '65000')->assertRedirect();
    $price('SPJ_REFERENCE', '-1')->assertSessionHasErrors('amount');

    expect(DB::table('product_price_history')->where('product_id', $product->id)->count())->toBe(3)
        ->and(DB::table('purchase_lines')->where('id', $line->id)->value('unit_price'))->toBe('45000.00')
        ->and(DB::table('audit_events')->where('action', 'CAT.DefaultPriceChanged')->count())->toBe(3);
    $this->actingAs($this->admin)->get("/data-master/produk?produk={$product->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('product.prices.0.kind', 'PURCHASE_DEFAULT')
            ->where('product.prices.0.history.0.amount', '52000.50')->where('product.prices.0.history.1.amount', '50000.00')
            ->where('product.prices.1.history.0.amount', '65000.00')->where('product.prices.2.history', []));
});

it('attaches the actor\'s own product image, the first its primary, and refuses another\'s file as unknown (FL-02; DIR-089 (4))', function () {
    $product = createdProduct($this);
    $mine = app(Uploads::class)->accept(Files::path(Files::png()), 'produk.png', FilePurpose::ProductImage, null, $this->admin->id);
    $other = app(Uploads::class)->accept(Files::path(Files::png()), 'lain.png', FilePurpose::ProductImage, null, Authorization::admin([$this->company->id], ['products.edit'])->id);

    $this->actingAs($this->admin)->put("/data-master/produk/{$product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'image', 'file' => $mine->public_id])->assertRedirect();
    expect(DB::table('product_images')->where('product_id', $product->id)->value('is_primary'))->toBeTrue();

    $this->actingAs($this->admin)->put("/data-master/produk/{$product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'image', 'file' => $other->public_id])->assertNotFound();
    $this->actingAs($this->admin)->get('/data-master/produk')->assertInertia(fn (AssertableInertia $page) => $page->where('list.rows.0.thumbnail', $mine->public_id));
});

it('archives a product, kept with its history and offered for no new demand or purchase (criterion 7; WF-MD-04)', function () {
    $product = createdProduct($this, ['name' => 'Arsip Produk Uji']);

    $this->actingAs($this->admin)->put("/data-master/produk/{$product->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'archive', 'lock_version' => 0, 'reason' => 'Tidak dijual'])->assertRedirect();
    expect(productAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($product->refresh()->is_active)->toBeFalse()
        ->and(app(App\Modules\CAT\Queries\ProductLookup::class)->names(new App\Support\Search\Term('Arsip Produk'), 20)['rows'])->toBe([]);

    $this->actingAs($this->admin)->post("/data-master/produk/{$product->public_id}/barcode", ['command_id' => (string) Str::uuid(), 'kind' => 'INTERNAL'])->assertRedirect();
    expect(productAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'WF-MD-04']);
});
