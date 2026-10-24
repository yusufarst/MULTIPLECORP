<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\InvoiceTaxComponent;
use App\Modules\FIN\Models\InvoiceVersion;
use App\Modules\ORG\Models\TaxType;
use Database\Factories\ORG\TaxTreatmentRuleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented output-tax component of a DRAFT version: 11% of Rp1.000.000 under an invented treatment rule.
 *
 * @extends Factory<InvoiceTaxComponent>
 */
final class InvoiceTaxComponentFactory extends Factory
{
    protected $model = InvoiceTaxComponent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_version_id' => InvoiceVersion::factory(),
            'tax_type_id' => TaxType::factory(),
            'treatment_rule_id' => static fn (array $row): int => TaxTreatmentRuleFactory::new()->createOne(['tax_type_id' => $row['tax_type_id']])->id,
            'treatment' => 'OUTPUT_EXCLUDED_FROM_REVENUE',
            'rate_percent' => '11.000000',
            'base_amount' => '1000000.00',
            'amount' => '110000.00',
        ];
    }
}
