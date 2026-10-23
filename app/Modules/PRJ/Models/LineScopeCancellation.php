<?php

namespace App\Modules\PRJ\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\PRJ\LineScopeCancellationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The cancellation of a line's remaining scope (DATABASE §4.5 `line_scope_cancellations`, AD;
 * SF-CANCEL-LINE; AX-14): once per line per command, corrected by a superseding decision (L-32).
 *
 * @property int $id
 * @property int $project_line_id
 * @property int $company_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $qty_base
 * @property string $reason
 * @property int $decided_by
 * @property CarbonImmutable $business_date
 * @property int|null $supersedes_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(LineScopeCancellationFactory::class)]
class LineScopeCancellation extends Model
{
    use AppendOnly;

    /** @use HasFactory<LineScopeCancellationFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<ProjectLine, $this> */
    public function projectLine(): BelongsTo
    {
        return $this->belongsTo(ProjectLine::class);
    }

    /** @return BelongsTo<LineScopeCancellation, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(LineScopeCancellation::class);
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
            'project_line_id' => 'integer',
            'company_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'qty_base' => 'decimal:3',
            'decided_by' => 'integer',
            'business_date' => 'immutable_date',
            'supersedes_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
