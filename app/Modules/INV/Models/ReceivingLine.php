<?php

namespace App\Modules\INV\Models;

use App\Modules\CAT\Models\Unit;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\ReceivingLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A receipt of a purchase line in its three outcomes (DATABASE §4.7 `receiving_lines`, AF; L-13): a
 * correction cites a receipt of the same line, the original delivery note once per line (C-49).
 *
 * @property int $id
 * @property int $stock_movement_id
 * @property int $company_id
 * @property int $purchase_line_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property int $ratio_to_base
 * @property string $qty_base
 * @property string $usable_qty_base
 * @property string $damaged_qty_base
 * @property string $rejected_qty_base
 * @property string $receipt_kind
 * @property int|null $corrects_receiving_line_id
 * @property int|null $correction_case_id
 * @property string|null $supplier_delivery_reference
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(ReceivingLineFactory::class)]
class ReceivingLine extends Model
{
    use AppendOnly;

    /** @use HasFactory<ReceivingLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<StockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** @return BelongsTo<PurchaseLine, $this> */
    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<ReceivingLine, $this> */
    public function corrects(): BelongsTo
    {
        return $this->belongsTo(ReceivingLine::class, 'corrects_receiving_line_id');
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
            'company_id' => 'integer',
            'purchase_line_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'qty_base' => 'decimal:3',
            'usable_qty_base' => 'decimal:3',
            'damaged_qty_base' => 'decimal:3',
            'rejected_qty_base' => 'decimal:3',
            'corrects_receiving_line_id' => 'integer',
            'correction_case_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
