<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\StockConsumption;
use App\Modules\INV\Models\StockRestoration;
use Database\Factories\CAT\ProductFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented loss reversal of one piece, in the product's base unit — a loss has no unit of its own (L-31)
 * —, under a RESTORATION movement of the consumption's source company.
 *
 * @extends Factory<StockRestoration>
 */
final class StockRestorationFactory extends Factory
{
    protected $model = StockRestoration::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'stock_consumption_id' => StockConsumption::factory(),
            'source_company_id' => static fn (array $row): int => StockConsumption::query()->whereKey($row['stock_consumption_id'])->firstOrFail()->source_company_id,
            'product_id' => static fn (array $row): int => StockConsumption::query()->whereKey($row['stock_consumption_id'])->firstOrFail()->product_id,
            'quantity_scale' => static fn (array $row): int => StockConsumption::query()->whereKey($row['stock_consumption_id'])->firstOrFail()->quantity_scale,
            'stock_movement_id' => static fn (array $row): int => StockMovementFactory::post('RESTORATION', $row['source_company_id']),
            'entered_qty' => '1.000',
            'entered_unit_id' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['base_unit_id'],
            'ratio_to_base' => 1,
            'qty_base' => '1.000',
            'kind' => 'LOSS_REVERSAL',
            'returned_condition' => 'USABLE',
        ];
    }
}
