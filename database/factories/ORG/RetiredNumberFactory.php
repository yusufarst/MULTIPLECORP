<?php

namespace Database\Factories\ORG;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\RetiredNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * The tombstone of an invented discarded draft project.
 *
 * @extends Factory<RetiredNumber>
 */
final class RetiredNumberFactory extends Factory
{
    protected $model = RetiredNumber::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'company_id' => Company::factory(),
            'sequence_type' => 'PROJECT',
            'period_key' => '2026',
            'sequence_no' => $number,
            'formatted_number' => sprintf('CTH/PRJ/2026/%04d', $number),
            'retired_kind' => 'DISCARDED_DRAFT_PROJECT',
            'source_public_id' => (string) Str::uuid7(),
            'reason' => 'Draf proyek contoh dibuang',
            'retired_by' => User::factory(),
        ];
    }
}
