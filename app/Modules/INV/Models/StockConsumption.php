<?php

namespace App\Modules\INV\Models;

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\StockConsumptionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A draw of lot quantity and its inter-company allocation overlay (DATABASE §4.7 `stock_consumptions`,
 * AF; §6.5; D-DB-14): its source the lot's company, its bearer keyed to its cause (C-28).
 *
 * @property int $id
 * @property int $stock_movement_id
 * @property int $lot_id
 * @property int $source_company_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $qty_base
 * @property string $kind
 * @property int|null $dispatch_line_id
 * @property int|null $unattributed_resolution_line_id
 * @property int $bearer_company_id
 * @property int|null $bearer_project_id
 * @property string|null $allocation_reason
 * @property int|null $allocation_authorized_by
 * @property int|null $correction_case_id
 * @property int|null $case_company_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(StockConsumptionFactory::class)]
class StockConsumption extends Model
{
    use AppendOnly;

    /** @use HasFactory<StockConsumptionFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<StockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** @return BelongsTo<InventoryLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    /** @return BelongsTo<DispatchLine, $this> */
    public function dispatchLine(): BelongsTo
    {
        return $this->belongsTo(DispatchLine::class);
    }

    /** @return BelongsTo<UnattributedLossResolutionLine, $this> */
    public function resolutionLine(): BelongsTo
    {
        return $this->belongsTo(UnattributedLossResolutionLine::class, 'unattributed_resolution_line_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function bearerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function bearerProject(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<CorrectionCase, $this> */
    public function correctionCase(): BelongsTo
    {
        return $this->belongsTo(CorrectionCase::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stock_movement_id' => 'integer',
            'lot_id' => 'integer',
            'source_company_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'qty_base' => 'decimal:3',
            'dispatch_line_id' => 'integer',
            'unattributed_resolution_line_id' => 'integer',
            'bearer_company_id' => 'integer',
            'bearer_project_id' => 'integer',
            'allocation_authorized_by' => 'integer',
            'correction_case_id' => 'integer',
            'case_company_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
