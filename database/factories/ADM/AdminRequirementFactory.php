<?php

namespace Database\Factories\ADM;

use App\Modules\ADM\Models\AdminRequirement;
use App\Modules\PRJ\Models\Project;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented required free-text item of a project's checklist, satisfied by an upload.
 *
 * @extends Factory<AdminRequirement>
 */
final class AdminRequirementFactory extends Factory
{
    protected $model = AdminRequirement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => static fn (array $row): int => ProjectFactory::companyOf($row['project_id']),
            'requirement_kind' => 'FREE_TEXT',
            'description' => 'Berita acara serah terima ditandatangani klien',
            'satisfaction_mode' => 'UPLOADED',
        ];
    }

    /** A requirement for the client's original, satisfied only by an upload of it (FS-12). */
    public function clientOriginal(): self
    {
        return $this->state(['requirement_kind' => 'EXTERNAL_DOCUMENT', 'evidence_type' => 'CLIENT_ORDER', 'requires_client_original' => true]);
    }
}
