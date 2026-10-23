<?php

namespace Database\Factories\PUR;

use App\Modules\PUR\Models\PurchaseLine;
use App\Modules\PUR\Models\PurchaseLineAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented price correction lowering a purchase line's value.
 *
 * @extends Factory<PurchaseLineAdjustment>
 */
final class PurchaseLineAdjustmentFactory extends Factory
{
    protected $model = PurchaseLineAdjustment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_line_id' => PurchaseLine::factory(),
            'company_id' => static fn (array $row): int => PurchaseLineFactory::keyOf($row['purchase_line_id'])['company_id'],
            'kind' => 'PRICE_CORRECTION',
            'amount_delta' => '-10000.00',
            'reason' => 'Harga disesuaikan dengan tagihan pemasok',
        ];
    }
}
