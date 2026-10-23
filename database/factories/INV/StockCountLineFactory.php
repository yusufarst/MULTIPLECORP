<?php

namespace Database\Factories\INV;

use App\Modules\CAT\Models\Product;
use App\Modules\IAM\Models\User;
use App\Modules\INV\Models\StockCount;
use App\Modules\INV\Models\StockCountLine;
use Database\Factories\CAT\ProductFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented count of three pieces of a product in its count's scope, the scope opened with the count.
 *
 * @extends Factory<StockCountLine>
 */
final class StockCountLineFactory extends Factory
{
    protected $model = StockCountLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'stock_count_id' => StockCount::factory(),
            'product_id' => Product::factory(),
            'quantity_scale' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['quantity_scale'],
            'location_id' => static fn (array $row): ?int => StockCount::query()->whereKey($row['stock_count_id'])->firstOrFail()->location_id,
            'condition' => static fn (array $row): ?string => StockCount::query()->whereKey($row['stock_count_id'])->firstOrFail()->condition,
            'counted_qty' => '3.000',
            'counted_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(static function (StockCountLine $line): void {
            StockCountFactory::openScope($line->stock_count_id, $line->product_id, $line->quantity_scale);
        });
    }
}
