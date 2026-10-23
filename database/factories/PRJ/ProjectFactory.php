<?php

namespace Database\Factories\PRJ;

use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PTY\Models\ClientOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented DRAFT project of a regular client, its number its company's PROJECT number of 2026.
 *
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'company_id' => Company::factory(),
            'project_number' => sprintf('CTH/PRJ/2026/%04d', $number),
            'period_key' => '2026',
            'sequence_no' => $number,
            'client_organization_id' => ClientOrganization::factory(),
            'channel' => 'REGULAR',
            'title' => "Pengadaan contoh {$number}",
        ];
    }

    /** An ACTIVE project. */
    public function active(): self
    {
        return $this->state(['state' => 'ACTIVE']);
    }

    /** The company of an invented row's project (C-32). */
    public static function companyOf(mixed $project): int
    {
        return Project::query()->whereKey($project)->firstOrFail()->company_id;
    }
}
