<?php

namespace App\Modules\INV\Models;

use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\UnattributedLossResolutionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The economic resolution of a pending case (DATABASE §4.7 `unattributed_loss_resolutions`, AD; AX-37),
 * reversed at most once and superseded within its case (C-37).
 *
 * @property int $id
 * @property int $unattributed_loss_case_id
 * @property string $resolution_kind
 * @property int $stock_movement_id
 * @property int|null $supersedes_resolution_id
 * @property int $decided_by
 * @property CarbonImmutable $business_date
 * @property string $reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(UnattributedLossResolutionFactory::class)]
class UnattributedLossResolution extends Model
{
    use AppendOnly;

    /** @use HasFactory<UnattributedLossResolutionFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<UnattributedLossCase, $this> */
    public function unattributedCase(): BelongsTo
    {
        return $this->belongsTo(UnattributedLossCase::class, 'unattributed_loss_case_id');
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** @return BelongsTo<UnattributedLossResolution, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(UnattributedLossResolution::class, 'supersedes_resolution_id');
    }

    /** @return HasMany<UnattributedLossResolutionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(UnattributedLossResolutionLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unattributed_loss_case_id' => 'integer',
            'stock_movement_id' => 'integer',
            'supersedes_resolution_id' => 'integer',
            'decided_by' => 'integer',
            'business_date' => 'immutable_date',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
