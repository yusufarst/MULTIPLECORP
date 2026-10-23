<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\Location;
use App\Modules\INV\Models\StockCount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * An invented OPEN count of a location's usable stock, started in the current transaction.
 *
 * @extends Factory<StockCount>
 */
final class StockCountFactory extends Factory
{
    protected $model = StockCount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'condition' => 'USABLE',
        ];
    }

    /**
     * The count's typed basis for a product at its location and condition, written as the opening
     * transaction writes it (§4.7, §5.6).
     */
    public static function openScope(int $count, int $product, int $scale = 0): void
    {
        $row = StockCount::query()->whereKey($count)->firstOrFail(['location_id', 'condition']);

        DB::table('stock_count_scopes')->insertOrIgnore([
            'stock_count_id' => $count, 'product_id' => $product, 'quantity_scale' => $scale, 'location_id' => $row->location_id,
            'condition' => $row->condition, 'basis_version' => 0, 'system_qty_at_start' => 0,
        ]);
    }
}
