<?php

namespace App\Modules\INV\Models;

use App\Modules\CAT\Models\Unit;
use App\Modules\OPS\Models\CorrectionCase;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\StockRestorationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The return of consumed units or of a drop-ship line (DATABASE §4.7 `stock_restorations`, AF;
 * SF-RESTORE), in the cited event's unit or the base unit (L-31), capped by its consumption guard (C-27).
 *
 * @property int $id
 * @property int|null $stock_movement_id
 * @property int|null $stock_consumption_id
 * @property int|null $dropship_confirmation_line_id
 * @property int $source_company_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property int|null $cited_dispatch_line_id
 * @property int|null $cited_unit_id
 * @property int|null $cited_ratio_to_base
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property int $ratio_to_base
 * @property string $qty_base
 * @property string $kind
 * @property bool|null $after_delivery
 * @property int|null $reverses_restoration_id
 * @property string|null $reverses_restoration_kind
 * @property string|null $returned_condition
 * @property int|null $correction_case_id
 * @property int|null $case_company_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(StockRestorationFactory::class)]
class StockRestoration extends Model
{
    use AppendOnly;

    /** @use HasFactory<StockRestorationFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<StockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** @return BelongsTo<StockConsumption, $this> */
    public function consumption(): BelongsTo
    {
        return $this->belongsTo(StockConsumption::class, 'stock_consumption_id');
    }

    /** @return BelongsTo<DispatchLine, $this> */
    public function citedDispatchLine(): BelongsTo
    {
        return $this->belongsTo(DispatchLine::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<StockRestoration, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(StockRestoration::class, 'reverses_restoration_id');
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
            'stock_consumption_id' => 'integer',
            'dropship_confirmation_line_id' => 'integer',
            'source_company_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'cited_dispatch_line_id' => 'integer',
            'cited_unit_id' => 'integer',
            'cited_ratio_to_base' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'qty_base' => 'decimal:3',
            'after_delivery' => 'boolean',
            'reverses_restoration_id' => 'integer',
            'correction_case_id' => 'integer',
            'case_company_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
