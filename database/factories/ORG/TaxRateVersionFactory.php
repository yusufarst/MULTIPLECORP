<?php

namespace Database\Factories\ORG;

use App\Modules\ORG\Models\TaxRateVersion;
use App\Modules\ORG\Models\TaxType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented rate on its whole base from 2026 onward.
 *
 * @extends Factory<TaxRateVersion>
 */
final class TaxRateVersionFactory extends Factory
{
    protected $model = TaxRateVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tax_type_id' => TaxType::factory(),
            'rate_percent' => '11.000000',
            'rounding_mode' => 'HALF_UP',
            'effective' => '[2026-01-01,)',
        ];
    }
}
