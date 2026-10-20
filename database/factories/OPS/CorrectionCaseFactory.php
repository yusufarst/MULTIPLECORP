<?php

namespace Database\Factories\OPS;

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open case of an invented company with its counters at zero.
 *
 * @extends Factory<CorrectionCase>
 */
final class CorrectionCaseFactory extends Factory
{
    protected $model = CorrectionCase::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'case_kind' => 'OTHER',
            'reason' => 'Kasus koreksi contoh',
        ];
    }
}
