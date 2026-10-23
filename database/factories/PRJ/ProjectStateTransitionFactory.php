<?php

namespace Database\Factories\PRJ;

use App\Modules\IAM\Models\User;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectStateTransition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An invented activation of a project, by its own command.
 *
 * @extends Factory<ProjectStateTransition>
 */
final class ProjectStateTransitionFactory extends Factory
{
    protected $model = ProjectStateTransition::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => static fn (array $row): int => ProjectFactory::companyOf($row['project_id']),
            'from_state' => 'DRAFT',
            'to_state' => 'ACTIVE',
            'transition_kind' => 'ACTIVATE',
            'decided_by' => User::factory(),
            'command_id' => static fn (): string => (string) Str::uuid7(),
            'command_line_no' => 1,
        ];
    }
}
