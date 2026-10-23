<?php

namespace Database\Factories\ORG;

use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyTaxProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented registration status from 2026 onward.
 *
 * @extends Factory<CompanyTaxProfile>
 */
final class CompanyTaxProfileFactory extends Factory
{
    protected $model = CompanyTaxProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'registration_status' => 'TERDAFTAR_CONTOH',
            'effective' => '[2026-01-01,)',
        ];
    }
}
