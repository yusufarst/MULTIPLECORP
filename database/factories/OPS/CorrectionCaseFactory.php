<?php

namespace Database\Factories\OPS;

use App\Modules\OPS\Models\CorrectionCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open case with its counters at zero. Until ORG's companies exist (TASK-006), the company is a
 * synthetic key.
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
            'company_id' => 1001,
            'case_kind' => 'OTHER',
            'reason' => 'Kasus koreksi contoh',
        ];
    }
}
