<?php

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductBarcode;
use App\Modules\CAT\Models\ProductImage;
use App\Modules\CAT\Models\ProductPriceHistory;
use App\Modules\CAT\Models\ProductUnit;
use App\Modules\CAT\Models\Unit;
use App\Modules\IAM\Models\User;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Support\Database\DatabaseRole;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-006), the CAT and PTY rows of DATABASE §18 and §4.3–§4.4 and §7: a barcode resolves to one
// active product however it is typed (C-01), serialized goods are counted at scale 0, a base quantity
// holds its product's scale and the scale cannot change under a quantity that references it, alternate
// units are exact multiples of one base, and a client's units are unique by name.

uses(RefreshDatabase::class);

it('resolves an active code to one product however it is typed or scanned, its deactivation freeing it (C-01)', function () {
    $barcode = ProductBarcode::factory()->create(['code' => '0099 1234 5678']);

    expect(DB::table('product_barcodes')->where('id', $barcode->id)->value('code_key'))->toBe('009912345678');

    Refusal::assert(Refusal::UNIQUE, 'product_barcodes_c01_active_code', fn () => ProductBarcode::factory()->create(['code' => "009912345678\t"]));
    Refusal::assert(Refusal::UNIQUE, 'product_barcodes_c01_active_code', fn () => ProductBarcode::factory()->create(['code' => ' 0099 12345678 ', 'kind' => 'INTERNAL']));

    ProductBarcode::factory()->create(['code' => 'ab 12']);

    Refusal::assert(Refusal::UNIQUE, 'product_barcodes_c01_active_code', fn () => ProductBarcode::factory()->create(['code' => 'AB12']));

    $barcode->forceFill(['is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => User::factory()->create()->id, 'deactivated_reason' => 'Kode dipindah'])->save();

    expect(ProductBarcode::factory()->create(['code' => '009912345678'])->exists)->toBeTrue()
        // Leading zeros are kept: 12345678 is another code.
        ->and(ProductBarcode::factory()->create(['code' => '12345678'])->exists)->toBeTrue();

    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table product_barcodes', fn () => DB::table('product_barcodes')->where('id', $barcode->id)->update(['code' => '1']));
    Refusal::assert(Refusal::CHECK, 'product_barcodes_kind_code', fn () => ProductBarcode::factory()->create(['kind' => 'SUPPLIER']));
});

it('keeps serialized goods counted at scale 0, a scale up to 3 and a minimum at the product\'s scale (BR-INV-03; §7)', function (array $product, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, fn () => Product::factory()->create($product));
})->with([
    'serialized at scale 2' => [['is_serialized' => true, 'quantity_scale' => 2], 'products_serialized_counted'],
    'a serialized service' => [['kind' => 'SERVICE', 'is_serialized' => true], 'products_serialized_counted'],
    'scale 4' => [['quantity_scale' => 4], 'products_quantity_scale_range'],
    'a fractional minimum of a counted product' => [['min_stock_qty_base' => '1.5'], 'products_minimum_at_scale'],
    'an unknown kind' => [['kind' => 'BUNDLE'], 'products_kind_code'],
]);

it('accepts measured goods at their scale and serialized counted goods', function () {
    expect(Product::factory()->create(['quantity_scale' => 3, 'min_stock_qty_base' => '1.250'])->min_stock_qty_base)->toBe('1.250')
        ->and(Product::factory()->create(['is_serialized' => true])->exists)->toBeTrue()
        ->and(Product::factory()->create(['kind' => 'SERVICE', 'sku' => null])->exists)->toBeTrue();
});

it('keeps one SKU whatever its case (§20)', function () {
    Product::factory()->create(['sku' => 'BRG-Contoh-1']);

    Refusal::assert(Refusal::UNIQUE, 'products_sku_unique', fn () => Product::factory()->create(['sku' => 'brg-contoh-1']));
});

it('blocks a scale change while a quantity references the product by its scale key (§1, §7)', function () {
    $owner = DB::connection(DatabaseRole::MIGRATION_CONNECTION);
    $owner->beginTransaction();

    try {
        $unit = $owner->table('units')->insertGetId(['code' => 'SAT-K', 'name' => 'Satuan Kunci']);
        $product = $owner->table('products')->insertGetId(['kind' => 'GOODS', 'name' => 'Barang Kunci', 'base_unit_id' => $unit, 'quantity_scale' => 0]);
        // The way every quantity-bearing fact of TASK-007–TASK-009 references a product.
        $owner->statement('create table quantity_reference (product_id bigint not null, quantity_scale smallint not null, qty_base numeric(18,3) not null,
            foreign key (product_id, quantity_scale) references products (id, quantity_scale), check (qty_base = round(qty_base, quantity_scale)))');
        $owner->table('quantity_reference')->insert(['product_id' => $product, 'quantity_scale' => 0, 'qty_base' => 5]);

        Refusal::assert(Refusal::FOREIGN_KEY, 'quantity_reference', fn () => $owner->table('products')->where('id', $product)->update(['quantity_scale' => 2]), DatabaseRole::MIGRATION_CONNECTION);
        Refusal::assert(Refusal::FOREIGN_KEY, 'quantity_reference', fn () => $owner->table('quantity_reference')->insert(['product_id' => $product, 'quantity_scale' => 3, 'qty_base' => 1.5]), DatabaseRole::MIGRATION_CONNECTION);
        Refusal::assert(Refusal::CHECK, 'quantity_reference', fn () => $owner->table('quantity_reference')->insert(['product_id' => $product, 'quantity_scale' => 0, 'qty_base' => 1.5]), DatabaseRole::MIGRATION_CONNECTION);
    } finally {
        $owner->rollBack();
    }
});

it('keeps alternate units exact integer multiples of one base unit at ratio 1 (L-31; §7)', function () {
    $product = Product::factory()->create();
    $unit = Unit::factory()->create();
    ProductUnit::factory()->for($product)->create(['unit_id' => $product->base_unit_id, 'ratio_to_base' => 1, 'is_base' => true]);
    ProductUnit::factory()->for($product)->for($unit)->create(['ratio_to_base' => 12]);

    Refusal::assert(Refusal::UNIQUE, 'product_units_one_base', fn () => ProductUnit::factory()->for($product)->create(['ratio_to_base' => 1, 'is_base' => true]));
    Refusal::assert(Refusal::UNIQUE, 'product_units_unit_unique', fn () => ProductUnit::factory()->for($product)->for($unit)->create(['ratio_to_base' => 24]));
    Refusal::assert(Refusal::CHECK, 'product_units_base_ratio_one', fn () => ProductUnit::factory()->create(['ratio_to_base' => 6, 'is_base' => true]));
    Refusal::assert(Refusal::CHECK, 'product_units_ratio_positive', fn () => ProductUnit::factory()->create(['ratio_to_base' => 0]));
});

it('keeps one unit per name within a client organization, whatever the case (§4.3)', function () {
    $organization = ClientOrganization::factory()->create();
    ClientUnit::factory()->for($organization, 'organization')->create(['name' => 'Bagian Keuangan']);

    Refusal::assert(Refusal::UNIQUE, 'client_units_name_unique', fn () => ClientUnit::factory()->for($organization, 'organization')->create(['name' => 'bagian keuangan']));

    expect(ClientUnit::factory()->create(['name' => 'Bagian Keuangan'])->exists)->toBeTrue()
        // No UNIQUE on an organization's name: same-named schools in two cities are legitimate.
        ->and(ClientOrganization::factory()->create(['name' => $organization->name])->exists)->toBeTrue();
});

it('keeps a default price at or above zero and its history as recorded (CAP-03; C-53)', function () {
    $price = ProductPriceHistory::factory()->create();

    Refusal::assert(Refusal::CHECK, 'product_price_history_amount_not_negative', fn () => ProductPriceHistory::factory()->create(['amount' => '-1.00']));
    Refusal::assert(Refusal::CHECK, 'product_price_history_kind_code', fn () => ProductPriceHistory::factory()->create(['price_kind' => 'CLEARANCE']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table product_price_history', fn () => DB::table('product_price_history')->where('id', $price->id)->update(['amount' => '1.00']));

    expect(fn () => $price->forceFill(['amount' => '1.00'])->save())->toThrow(LogicException::class, 'append-only');
});

it('keeps one primary image per product (§4.4)', function () {
    $image = ProductImage::factory()->create(['is_primary' => true]);

    Refusal::assert(Refusal::UNIQUE, 'product_images_one_primary', fn () => ProductImage::factory()->create(['product_id' => $image->product_id, 'is_primary' => true]));

    expect(ProductImage::factory()->create(['product_id' => $image->product_id, 'sort_order' => 1])->exists)->toBeTrue();
});
