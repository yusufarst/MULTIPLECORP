<?php

namespace Database\Factories\CAT;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Counted goods at scale 0, the commonest product.
 *
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'kind' => 'GOODS',
            'sku' => "BRG-CTH-{$number}",
            'name' => "Barang Contoh {$number}",
            'base_unit_id' => Unit::factory(),
            'quantity_scale' => 0,
        ];
    }
}
