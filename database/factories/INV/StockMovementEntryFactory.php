<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\Location;
use App\Modules\INV\Models\StockMovementEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented opening entry of one usable piece on a lot, under an OPENING movement of the lot's company.
 *
 * @extends Factory<StockMovementEntry>
 */
final class StockMovementEntryFactory extends Factory
{
    protected $model = StockMovementEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'lot_id' => InventoryLot::factory(),
            'product_id' => static fn (array $row): int => InventoryLotFactory::keyOf($row['lot_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => InventoryLotFactory::keyOf($row['lot_id'])['quantity_scale'],
            'movement_type' => 'OPENING',
            'stock_movement_id' => static fn (array $row): int => StockMovementFactory::post('OPENING', InventoryLotFactory::keyOf($row['lot_id'])['source_company_id']),
            'location_id' => Location::factory(),
            'condition' => 'USABLE',
            'qty_delta_base' => '1.000',
        ];
    }
}
