<?php

namespace Database\Factories\CAT;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductPriceHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented selling default.
 *
 * @extends Factory<ProductPriceHistory>
 */
final class ProductPriceHistoryFactory extends Factory
{
    protected $model = ProductPriceHistory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'price_kind' => 'SELLING_DEFAULT',
            'amount' => '150000.00',
            'effective_from' => '2026-01-01',
        ];
    }
}
