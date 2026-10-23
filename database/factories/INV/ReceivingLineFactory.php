<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\ReceivingLine;
use App\Modules\PUR\Models\PurchaseLine;
use Database\Factories\CAT\ProductFactory;
use Database\Factories\PUR\PurchaseLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented original receipt of ten usable pieces of a purchase line, under a RECEIPT movement of its
 * company, with its own delivery note.
 *
 * @extends Factory<ReceivingLine>
 */
final class ReceivingLineFactory extends Factory
{
    protected $model = ReceivingLine::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_line_id' => PurchaseLine::factory(),
            'company_id' => static fn (array $row): int => PurchaseLineFactory::keyOf($row['purchase_line_id'])['company_id'],
            'product_id' => static fn (array $row): int => PurchaseLineFactory::keyOf($row['purchase_line_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => PurchaseLineFactory::keyOf($row['purchase_line_id'])['quantity_scale'],
            'stock_movement_id' => static fn (array $row): int => StockMovementFactory::post('RECEIPT', $row['company_id']),
            'entered_qty' => '10.000',
            'entered_unit_id' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['base_unit_id'],
            'ratio_to_base' => 1,
            'qty_base' => '10.000',
            'usable_qty_base' => '10.000',
            'damaged_qty_base' => '0.000',
            'rejected_qty_base' => '0.000',
            'receipt_kind' => 'ORIGINAL',
            'supplier_delivery_reference' => 'SJ-CTH-'.++self::$sequence,
        ];
    }
}
