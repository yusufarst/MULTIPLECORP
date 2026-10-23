<?php

namespace App\Modules\INV\Models;

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PUR\Models\Purchase;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The header of an inventory command (DATABASE §4.7 `stock_movements`, AF; §5.3), of one company or of
 * the pool; a reversal names what it reverses by a typed pair (C-55).
 *
 * @property int $id
 * @property string $public_id
 * @property string $movement_type
 * @property CarbonImmutable $business_date
 * @property string|null $reason
 * @property int|null $company_id
 * @property int|null $project_id
 * @property int|null $purchase_id
 * @property int|null $correction_case_id
 * @property int|null $stock_count_id
 * @property int|null $unattributed_case_id
 * @property int|null $reverses_movement_id
 * @property string|null $reverses_movement_type
 * @property string|null $supplier_delivery_reference
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(StockMovementFactory::class)]
class StockMovement extends Model
{
    use AppendOnly;

    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

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

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<CorrectionCase, $this> */
    public function correctionCase(): BelongsTo
    {
        return $this->belongsTo(CorrectionCase::class);
    }

    /** @return BelongsTo<StockCount, $this> */
    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    /** @return BelongsTo<UnattributedLossCase, $this> */
    public function unattributedCase(): BelongsTo
    {
        return $this->belongsTo(UnattributedLossCase::class);
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'reverses_movement_id');
    }

    /** @return HasMany<StockMovementEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(StockMovementEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'business_date' => 'immutable_date',
            'company_id' => 'integer',
            'project_id' => 'integer',
            'purchase_id' => 'integer',
            'correction_case_id' => 'integer',
            'stock_count_id' => 'integer',
            'unattributed_case_id' => 'integer',
            'reverses_movement_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
