<?php

namespace Database\Factories\INV;

use App\Modules\CAT\Models\Product;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\ORG\Models\Company;
use Database\Factories\CAT\ProductFactory;
use Database\Factories\OPS\ImportRowFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented opening lot of ten pieces, keyed to an import row of its source company (§4.7; C-08).
 *
 * @extends Factory<InventoryLot>
 */
final class InventoryLotFactory extends Factory
{
    protected $model = InventoryLot::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'lot_code' => "LOT-CTH-{$number}",
            'product_id' => Product::factory(),
            'quantity_scale' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['quantity_scale'],
            'source_company_id' => Company::factory(),
            'acquisition_kind' => 'OPENING',
            'import_row_id' => static fn (array $row): int => ImportRowFactory::new()->createOne(['company_id' => $row['source_company_id']])->id,
            'entered_qty' => '10.000',
            'entered_unit_id' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['base_unit_id'],
            'ratio_to_base' => 1,
            'acquired_qty_base' => '10.000',
            'business_date' => '2026-10-01',
        ];
    }

    /**
     * The product, scale and source company a row citing the lot takes from it (§4.7; C-09, C-28).
     *
     * @return array{product_id: int, quantity_scale: int, source_company_id: int}
     */
    public static function keyOf(mixed $lot): array
    {
        $row = InventoryLot::query()->whereKey($lot)->firstOrFail(['product_id', 'quantity_scale', 'source_company_id']);

        return ['product_id' => $row->product_id, 'quantity_scale' => $row->quantity_scale, 'source_company_id' => $row->source_company_id];
    }
}
