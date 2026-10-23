<?php

namespace Database\Factories\INV;

use App\Modules\CAT\Models\Product;
use App\Modules\INV\Models\StockCount;
use App\Modules\INV\Models\StockCountFinding;
use Database\Factories\CAT\ProductFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented OPEN surplus of one piece in its count's scope, the scope opened with the count.
 *
 * @extends Factory<StockCountFinding>
 */
final class StockCountFindingFactory extends Factory
{
    protected $model = StockCountFinding::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'stock_count_id' => StockCount::factory(),
            'product_id' => Product::factory(),
            'quantity_scale' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['quantity_scale'],
            'location_id' => static fn (array $row): ?int => StockCount::query()->whereKey($row['stock_count_id'])->firstOrFail()->location_id,
            'condition' => static fn (array $row): ?string => StockCount::query()->whereKey($row['stock_count_id'])->firstOrFail()->condition,
            'surplus_qty_base' => '1.000',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(static function (StockCountFinding $finding): void {
            StockCountFactory::openScope($finding->stock_count_id, $finding->product_id, $finding->quantity_scale);
        });
    }
}
