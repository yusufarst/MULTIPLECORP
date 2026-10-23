<?php

namespace Database\Factories\PRJ;

use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\QuotationRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented DRAFT revision of its project's quotation.
 *
 * @extends Factory<QuotationRevision>
 */
final class QuotationRevisionFactory extends Factory
{
    protected $model = QuotationRevision::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => static fn (array $row): int => ProjectFactory::companyOf($row['project_id']),
            'revision_no' => ++self::$sequence,
            'valid_until' => '2026-11-30',
        ];
    }
}
