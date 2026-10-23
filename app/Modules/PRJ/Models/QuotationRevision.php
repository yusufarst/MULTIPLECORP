<?php

namespace App\Modules\PRJ\Models;

use App\Modules\IAM\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PRJ\QuotationRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A revision of a project's quotation (DATABASE §4.5 `quotation_revisions`, SR; SM:Quotation): one linear
 * chain per project, its lines frozen once SENT, EXPIRED derived and never voided (BR-CR-06).
 *
 * @property int $id
 * @property string $public_id
 * @property int $project_id
 * @property int $company_id
 * @property int $revision_no
 * @property string $state
 * @property CarbonImmutable|null $valid_until
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_reason
 * @property int|null $supersedes_revision_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(QuotationRevisionFactory::class)]
class QuotationRevision extends Model
{
    /** @use HasFactory<QuotationRevisionFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<QuotationRevision, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class, 'supersedes_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return HasMany<QuotationRevisionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationRevisionLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'company_id' => 'integer',
            'revision_no' => 'integer',
            'valid_until' => 'immutable_date',
            'decided_by' => 'integer',
            'decided_at' => 'immutable_datetime',
            'supersedes_revision_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
