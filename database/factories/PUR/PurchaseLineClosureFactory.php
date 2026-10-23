<?php

namespace Database\Factories\PUR;

use App\Modules\IAM\Models\User;
use App\Modules\PUR\Models\PurchaseLine;
use App\Modules\PUR\Models\PurchaseLineClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented closure of two pieces of a purchase line's remainder.
 *
 * @extends Factory<PurchaseLineClosure>
 */
final class PurchaseLineClosureFactory extends Factory
{
    protected $model = PurchaseLineClosure::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_line_id' => PurchaseLine::factory(),
            'company_id' => static fn (array $row): int => PurchaseLineFactory::keyOf($row['purchase_line_id'])['company_id'],
            'product_id' => static fn (array $row): int => PurchaseLineFactory::keyOf($row['purchase_line_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => PurchaseLineFactory::keyOf($row['purchase_line_id'])['quantity_scale'],
            'closed_qty_base' => '2.000',
            'reason' => 'Pemasok tidak dapat mengirim sisa barang',
            'decided_by' => User::factory(),
            'business_date' => '2026-10-01',
        ];
    }
}
