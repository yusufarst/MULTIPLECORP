<?php

namespace App\Modules\ADM\Models;

use App\Modules\DOC\Models\DocumentType;
use App\Modules\IAM\Models\User;
use App\Modules\PRJ\Models\Project;
use Carbon\CarbonImmutable;
use Database\Factories\ADM\AdminRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A project's administrative checklist item (DATABASE §4.11 `admin_requirements`, SR; BR-ADM-01):
 * tightened by an Admin, relaxed or removed by the Owner only (DIR-024 D-5), its satisfaction derived from
 * its links (WF-ADM-01).
 *
 * @property int $id
 * @property int $project_id
 * @property int $company_id
 * @property string $requirement_kind
 * @property string|null $document_type_code
 * @property string|null $evidence_type
 * @property string $description
 * @property bool $is_required
 * @property string $satisfaction_mode
 * @property bool $requires_client_original
 * @property bool $waiver_eligible
 * @property bool $is_default_invoice_item
 * @property string $state
 * @property int|null $removed_by
 * @property CarbonImmutable|null $removed_at
 * @property string|null $removed_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(AdminRequirementFactory::class)]
class AdminRequirement extends Model
{
    /** @use HasFactory<AdminRequirementFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<DocumentType, $this> */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_code', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    /** @return HasMany<AdminRequirementLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(AdminRequirementLink::class);
    }

    /** @return HasMany<AdminRequirementWaiver, $this> */
    public function waivers(): HasMany
    {
        return $this->hasMany(AdminRequirementWaiver::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'company_id' => 'integer',
            'is_required' => 'boolean',
            'requires_client_original' => 'boolean',
            'waiver_eligible' => 'boolean',
            'is_default_invoice_item' => 'boolean',
            'removed_by' => 'integer',
            'removed_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
