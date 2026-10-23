<?php

namespace Database\Factories\ORG;

use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\NumberingScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A yearly scheme for the invoice layout; the template is illustrative, the formats being GAP-019's.
 *
 * @extends Factory<NumberingScheme>
 */
final class NumberingSchemeFactory extends Factory
{
    protected $model = NumberingScheme::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'sequence_type' => 'DOC-06',
            'period_granularity' => 'YEAR',
            'format_template' => 'CTH/INV/{tahun}/{nomor}',
            'revision_format' => 'NEW_NUMBER',
            'effective_from' => '2026-01-01',
        ];
    }
}
