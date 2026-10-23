<?php

namespace Database\Factories\ORG;

use App\Modules\ORG\Models\TaxTreatmentRule;
use App\Modules\ORG\Models\TaxType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A global rule — every company — for an invented tax type from 2026 onward.
 *
 * @extends Factory<TaxTreatmentRule>
 */
final class TaxTreatmentRuleFactory extends Factory
{
    protected $model = TaxTreatmentRule::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'transaction_context' => 'SALES_INVOICE',
            'tax_type_id' => TaxType::factory(),
            'treatment' => 'OUTPUT_EXCLUDED_FROM_REVENUE',
            'effective' => '[2026-01-01,)',
        ];
    }
}
