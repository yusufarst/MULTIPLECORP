<?php

namespace App\Modules\INV\Models;

use App\Modules\CAT\Models\Product;
use App\Modules\PRJ\Models\Project;
use Carbon\CarbonImmutable;
use Database\Factories\INV\UnattributedLossCaseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pending unexplained loss or condition change in one scope (DATABASE §4.7 `unattributed_loss_cases`,
 * SR + GB; §5.8; DIR-026/027): its four counters the only updatable columns, its pending quantity never
 * negative (C-35, C-36).
 *
 * @property int $id
 * @property string $public_id
 * @property string $case_kind
 * @property int $product_id
 * @property int $quantity_scale
 * @property int $location_id
 * @property string $condition
 * @property string $recognized_qty_base
 * @property string $exposure_qty_base
 * @property string $prior_pending_qty_base
 * @property int|null $exposure_group_case_id
 * @property string $found_qty_base
 * @property string $resolved_qty_base
 * @property string $reversed_qty_base
 * @property string $converted_qty_base
 * @property string $pending_qty_base
 * @property int|null $causal_project_id
 * @property int $recognition_movement_id
 * @property int|null $converted_from_case_id
 * @property CarbonImmutable $business_date
 * @property int $recognized_by
 * @property string $reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(UnattributedLossCaseFactory::class)]
class UnattributedLossCase extends Model
{
    /** @use HasFactory<UnattributedLossCaseFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<UnattributedLossCase, $this> */
    public function groupFounder(): BelongsTo
    {
        return $this->belongsTo(UnattributedLossCase::class, 'exposure_group_case_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function causalProject(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function recognitionMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /** @return BelongsTo<UnattributedLossCase, $this> */
    public function convertedFrom(): BelongsTo
    {
        return $this->belongsTo(UnattributedLossCase::class, 'converted_from_case_id');
    }

    /** @return HasMany<UnattributedLossCandidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(UnattributedLossCandidate::class);
    }

    /** @return HasMany<UnattributedLossResolution, $this> */
    public function resolutions(): HasMany
    {
        return $this->hasMany(UnattributedLossResolution::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'location_id' => 'integer',
            'recognized_qty_base' => 'decimal:3',
            'exposure_qty_base' => 'decimal:3',
            'prior_pending_qty_base' => 'decimal:3',
            'exposure_group_case_id' => 'integer',
            'found_qty_base' => 'decimal:3',
            'resolved_qty_base' => 'decimal:3',
            'reversed_qty_base' => 'decimal:3',
            'converted_qty_base' => 'decimal:3',
            'pending_qty_base' => 'decimal:3',
            'causal_project_id' => 'integer',
            'recognition_movement_id' => 'integer',
            'converted_from_case_id' => 'integer',
            'business_date' => 'immutable_date',
            'recognized_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
