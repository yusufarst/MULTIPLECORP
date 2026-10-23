<?php

namespace Database\Factories\ADM;

use App\Modules\ADM\Models\AdminRequirement;
use App\Modules\ADM\Models\AdminRequirementWaiver;
use App\Modules\IAM\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented TIDAK BERLAKU decision on an eligible requirement of its company.
 *
 * @extends Factory<AdminRequirementWaiver>
 */
final class AdminRequirementWaiverFactory extends Factory
{
    protected $model = AdminRequirementWaiver::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'admin_requirement_id' => AdminRequirementFactory::new()->state(['waiver_eligible' => true]),
            'company_id' => static fn (array $row): int => AdminRequirement::query()->whereKey($row['admin_requirement_id'])->firstOrFail()->company_id,
            'reason' => 'Dokumen tidak diwajibkan untuk proyek ini',
            'decided_by' => User::factory(),
        ];
    }
}
