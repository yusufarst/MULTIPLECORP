<?php

namespace Database\Factories\PUR;

use App\Modules\PUR\Models\PurchaseCharge;
use App\Modules\PUR\Models\PurchaseChargeLineShare;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented first-version share of a charge on a new line of the charge's purchase.
 *
 * @extends Factory<PurchaseChargeLineShare>
 */
final class PurchaseChargeLineShareFactory extends Factory
{
    protected $model = PurchaseChargeLineShare::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_charge_id' => PurchaseCharge::factory(),
            'company_id' => static fn (array $row): int => PurchaseCharge::query()->whereKey($row['purchase_charge_id'])->firstOrFail()->company_id,
            'purchase_line_id' => static fn (array $row): int => PurchaseLineFactory::new()->createOne([
                'purchase_id' => PurchaseCharge::query()->whereKey($row['purchase_charge_id'])->firstOrFail()->purchase_id,
            ])->id,
            'basis_value' => '1000000.000',
            'share_amount' => '50000.00',
            'allocation_version' => 1,
        ];
    }
}
