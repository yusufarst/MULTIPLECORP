<?php

namespace Database\Factories\ADM;

use App\Modules\ADM\Models\AdminRequirement;
use App\Modules\ADM\Models\AdminRequirementLink;
use App\Modules\IAM\Models\User;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented link of a requirement to an evidence of its company (C-32).
 *
 * @extends Factory<AdminRequirementLink>
 */
final class AdminRequirementLinkFactory extends Factory
{
    protected $model = AdminRequirementLink::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'admin_requirement_id' => AdminRequirement::factory(),
            'company_id' => static fn (array $row): int => AdminRequirement::query()->whereKey($row['admin_requirement_id'])->firstOrFail()->company_id,
            'evidence_document_id' => static fn (array $row): int => EvidenceDocumentFactory::new()->createOne(['company_id' => $row['company_id']])->id,
            'linked_by' => User::factory(),
        ];
    }
}
