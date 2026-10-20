<?php

namespace Database\Factories\CAT;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductBarcode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented manufacturer code.
 *
 * @extends Factory<ProductBarcode>
 */
final class ProductBarcodeFactory extends Factory
{
    protected $model = ProductBarcode::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'code' => '0099'.str_pad((string) ++self::$sequence, 9, '0', STR_PAD_LEFT),
            'kind' => 'MANUFACTURER',
        ];
    }
}
