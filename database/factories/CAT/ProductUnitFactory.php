<?php

namespace Database\Factories\CAT;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductUnit;
use App\Modules\CAT\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An alternate unit of twelve base units.
 *
 * @extends Factory<ProductUnit>
 */
final class ProductUnitFactory extends Factory
{
    protected $model = ProductUnit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'unit_id' => Unit::factory(),
            'ratio_to_base' => 12,
        ];
    }
}
