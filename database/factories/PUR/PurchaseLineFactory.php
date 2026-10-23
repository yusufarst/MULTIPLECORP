<?php

namespace Database\Factories\PUR;

use App\Modules\CAT\Models\Product;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseLine;
use Database\Factories\CAT\ProductFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented warehouse line of ten pieces in the product's base unit, of its purchase's company and with
 * its product's scale (§1; C-09, C-32), before its first effect.
 *
 * @extends Factory<PurchaseLine>
 */
final class PurchaseLineFactory extends Factory
{
    protected $model = PurchaseLine::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'purchase_id' => Purchase::factory(),
            'company_id' => static fn (array $row): int => PurchaseFactory::companyOf($row['purchase_id']),
            'line_no' => $number,
            'kind' => 'GOODS',
            'product_id' => Product::factory(),
            'quantity_scale' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['quantity_scale'],
            'delivery_mode' => 'WAREHOUSE',
            'entered_qty' => '10.000',
            'entered_unit_id' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['base_unit_id'],
            'ratio_to_base' => 1,
            'qty_base' => '10.000',
            'unit_price' => '100000.00',
            'line_value' => '1000000.00',
            'description' => "Barang contoh dibeli {$number}",
        ];
    }

    /**
     * The key a closure cites this line through (§1; C-09, C-32).
     *
     * @return array{company_id: int, product_id: int, quantity_scale: int}
     */
    public static function keyOf(mixed $line): array
    {
        $row = PurchaseLine::query()->whereKey($line)->firstOrFail(['company_id', 'product_id', 'quantity_scale']);

        return ['company_id' => $row->company_id, 'product_id' => $row->product_id, 'quantity_scale' => $row->quantity_scale];
    }
}
