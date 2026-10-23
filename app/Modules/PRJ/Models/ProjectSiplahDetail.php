<?php

namespace App\Modules\PRJ\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PRJ\ProjectSiplahDetailFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A SIPLAH project's manual metadata (DATABASE §4.5 `project_siplah_details`, SR; CAP-12): informational,
 * never moving money (BR-FIN-10), locked after the first channel-dependent commit (BR-PRJ-05; C-51).
 *
 * @property int $project_id
 * @property string|null $external_reference
 * @property string|null $program_code
 * @property string|null $account_code
 * @property string|null $siplah_value
 * @property string|null $real_purchase_value
 * @property string|null $fee_amount
 * @property string|null $va_fee_amount
 * @property string|null $cashback_amount
 * @property string|null $refund_amount
 * @property string|null $notes
 * @property CarbonImmutable|null $locked_at
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ProjectSiplahDetailFactory::class)]
class ProjectSiplahDetail extends Model
{
    /** @use HasFactory<ProjectSiplahDetailFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    protected $primaryKey = 'project_id';

    public $incrementing = false;

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'siplah_value' => 'decimal:2',
            'real_purchase_value' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'va_fee_amount' => 'decimal:2',
            'cashback_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'locked_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
