<?php

namespace App\Modules\PRJ\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\PRJ\CommercialConfirmationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A project's commercial confirmation (DATABASE §4.5 `commercial_confirmations`, AD; BR-PRJ-06; AX-13):
 * one root per approved revision (C-49), corrected only by a superseding confirmation of the same
 * authority (CM-27; C-59).
 *
 * @property int $id
 * @property int $project_id
 * @property int $company_id
 * @property string $kind
 * @property int|null $quotation_revision_id
 * @property CarbonImmutable $business_date
 * @property int $decided_by
 * @property string|null $reason
 * @property int|null $supersedes_confirmation_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(CommercialConfirmationFactory::class)]
class CommercialConfirmation extends Model
{
    use AppendOnly;

    /** @use HasFactory<CommercialConfirmationFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<QuotationRevision, $this> */
    public function quotationRevision(): BelongsTo
    {
        return $this->belongsTo(QuotationRevision::class);
    }

    /** @return BelongsTo<CommercialConfirmation, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(CommercialConfirmation::class, 'supersedes_confirmation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return HasMany<ConfirmationLinePin, $this> */
    public function pins(): HasMany
    {
        return $this->hasMany(ConfirmationLinePin::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'company_id' => 'integer',
            'quotation_revision_id' => 'integer',
            'business_date' => 'immutable_date',
            'decided_by' => 'integer',
            'supersedes_confirmation_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
