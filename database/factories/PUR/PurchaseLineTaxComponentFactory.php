<?php

namespace Database\Factories\PUR;

use App\Modules\ORG\Models\TaxType;
use App\Modules\PUR\Models\PurchaseLine;
use App\Modules\PUR\Models\PurchaseLineTaxComponent;
use Database\Factories\ORG\TaxTreatmentRuleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented creditable input tax of 11 % on a line's value, under an invented purchase-line rule.
 *
 * @extends Factory<PurchaseLineTaxComponent>
 */
final class PurchaseLineTaxComponentFactory extends Factory
{
    protected $model = PurchaseLineTaxComponent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_line_id' => PurchaseLine::factory(),
            'tax_type_id' => TaxType::factory(),
            'treatment_rule_id' => static fn (array $row): int => TaxTreatmentRuleFactory::new()->createOne([
                'tax_type_id' => $row['tax_type_id'],
                'transaction_context' => 'PURCHASE_LINE',
                'treatment' => 'INPUT_CREDITABLE',
            ])->id,
            'treatment' => 'INPUT_CREDITABLE',
            'rate_percent' => '11.000000',
            'base_amount' => '1000000.00',
            'amount' => '110000.00',
        ];
    }
}
