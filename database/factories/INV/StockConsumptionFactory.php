<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\StockConsumption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented loss of one piece of a lot, borne by the lot's own company at company level, under a LOSS
 * movement of that company.
 *
 * @extends Factory<StockConsumption>
 */
final class StockConsumptionFactory extends Factory
{
    protected $model = StockConsumption::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'lot_id' => InventoryLot::factory(),
            'source_company_id' => static fn (array $row): int => InventoryLotFactory::keyOf($row['lot_id'])['source_company_id'],
            'product_id' => static fn (array $row): int => InventoryLotFactory::keyOf($row['lot_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => InventoryLotFactory::keyOf($row['lot_id'])['quantity_scale'],
            'stock_movement_id' => static fn (array $row): int => StockMovementFactory::post('LOSS', $row['source_company_id']),
            'qty_base' => '1.000',
            'kind' => 'LOSS',
            'bearer_company_id' => static fn (array $row): mixed => $row['source_company_id'],
        ];
    }
}
