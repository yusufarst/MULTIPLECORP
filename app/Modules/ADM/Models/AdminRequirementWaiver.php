<?php

namespace App\Modules\ADM\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\ADM\AdminRequirementWaiverFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TIDAK BERLAKU on an eligible requirement (DATABASE §4.11 `admin_requirement_waivers`, AD; BR-ADM-02):
 * corrected by a superseding decision (L-32), reopened by a material correction (BR-ADM-03).
 *
 * @property int $id
 * @property int $admin_requirement_id
 * @property int $company_id
 * @property string $reason
 * @property int $decided_by
 * @property int|null $supersedes_waiver_id
 * @property bool $reopened_by_correction
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(AdminRequirementWaiverFactory::class)]
class AdminRequirementWaiver extends Model
{
    use AppendOnly;

    /** @use HasFactory<AdminRequirementWaiverFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<AdminRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(AdminRequirement::class, 'admin_requirement_id');
    }

    /** @return BelongsTo<AdminRequirementWaiver, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(AdminRequirementWaiver::class, 'supersedes_waiver_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'admin_requirement_id' => 'integer',
            'company_id' => 'integer',
            'decided_by' => 'integer',
            'supersedes_waiver_id' => 'integer',
            'reopened_by_correction' => 'boolean',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
