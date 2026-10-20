<?php

namespace App\Modules\OPS\Models;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\CorrectionCaseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A correction case (DATABASE §4.13 `correction_cases`, SR; §13; BR-CR-04) gathering every action of one
 * correction. Its six counters are guard columns of DATABASE §3.1, exact rupiah carried as decimal
 * strings, written only by the posting services (C-46). The relations to its company, project and lot
 * arrive with the models of ORG, PRJ and INV.
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int|null $project_id
 * @property string $case_kind
 * @property string $state
 * @property string|null $reason
 * @property int|null $causal_project_id
 * @property int|null $source_lot_id
 * @property numeric-string $unbacked_refund_amount
 * @property numeric-string $residual_settlement_amount
 * @property numeric-string $returned_cost
 * @property numeric-string $refunded_value
 * @property numeric-string $replaced_value
 * @property numeric-string $unrecovered_value
 * @property string|null $closure_outcome
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property int|null $reopened_by
 * @property CarbonImmutable|null $reopened_at
 * @property string|null $reopen_reason
 * @property string|null $reopen_command_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(CorrectionCaseFactory::class)]
class CorrectionCase extends Model
{
    /** @use HasFactory<CorrectionCaseFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'project_id' => 'integer',
            'causal_project_id' => 'integer',
            'source_lot_id' => 'integer',
            'unbacked_refund_amount' => 'decimal:2',
            'residual_settlement_amount' => 'decimal:2',
            'returned_cost' => 'decimal:2',
            'refunded_value' => 'decimal:2',
            'replaced_value' => 'decimal:2',
            'unrecovered_value' => 'decimal:2',
            'closed_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
