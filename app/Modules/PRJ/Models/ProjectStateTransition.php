<?php

namespace App\Modules\PRJ\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\PRJ\ProjectStateTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project's activation, cancellation, completion, Force Complete or revalidation (DATABASE §4.5
 * `project_state_transitions`, AD; AX-25–27): written with the state, once per project per command, a
 * Force Complete with its reason, snapshot and confirmed hash (C-38).
 *
 * @property int $id
 * @property int $project_id
 * @property int $company_id
 * @property string $from_state
 * @property string $to_state
 * @property string $transition_kind
 * @property string|null $reason
 * @property array<string, mixed>|null $predicate_snapshot
 * @property array<string, mixed>|null $unmet_predicates
 * @property array<string, mixed>|null $residual_obligations
 * @property string|null $confirmed_snapshot_hash
 * @property string|null $correction_reference
 * @property int $decided_by
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(ProjectStateTransitionFactory::class)]
class ProjectStateTransition extends Model
{
    use AppendOnly;

    /** @use HasFactory<ProjectStateTransitionFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
            'project_id' => 'integer',
            'company_id' => 'integer',
            'predicate_snapshot' => 'array',
            'unmet_predicates' => 'array',
            'residual_obligations' => 'array',
            'decided_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
