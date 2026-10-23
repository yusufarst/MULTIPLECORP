<?php

namespace Database\Factories\PRJ;

use App\Modules\IAM\Models\User;
use App\Modules\PRJ\Models\CommercialConfirmation;
use App\Modules\PRJ\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An invented root confirmation of a client order without a quotation, by its own command.
 *
 * @extends Factory<CommercialConfirmation>
 */
final class CommercialConfirmationFactory extends Factory
{
    protected $model = CommercialConfirmation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => static fn (array $row): int => ProjectFactory::companyOf($row['project_id']),
            'kind' => 'NO_QUOTATION',
            'business_date' => '2026-10-01',
            'decided_by' => User::factory(),
            'command_id' => static fn (): string => (string) Str::uuid7(),
            'command_line_no' => 1,
        ];
    }
}
