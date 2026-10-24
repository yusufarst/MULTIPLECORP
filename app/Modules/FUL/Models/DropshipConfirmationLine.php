<?php

namespace App\Modules\FUL\Models;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use App\Modules\INV\Models\SerialUnit;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\PRJ\Models\ProjectLine;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FUL\DropshipConfirmationLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The confirmed quantity of a purchase line for a demand line (DATABASE §4.9
 * `dropship_confirmation_lines`, AF; L-18), at its ratio snapshot (C-09); a replacement shipment carries
 * its case.
 *
 * @property int $id
 * @property int $dropship_confirmation_id
 * @property int $company_id
 * @property int $purchase_line_id
 * @property int $project_line_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property int $ratio_to_base
 * @property string $qty_base
 * @property int|null $serial_unit_id
 * @property bool $is_replacement
 * @property int|null $correction_case_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(DropshipConfirmationLineFactory::class)]
class DropshipConfirmationLine extends Model
{
    use AppendOnly;

    /** @use HasFactory<DropshipConfirmationLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<DropshipConfirmation, $this> */
    public function confirmation(): BelongsTo
    {
        return $this->belongsTo(DropshipConfirmation::class, 'dropship_confirmation_id');
    }

    /** @return BelongsTo<PurchaseLine, $this> */
    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
    }

    /** @return BelongsTo<ProjectLine, $this> */
    public function projectLine(): BelongsTo
    {
        return $this->belongsTo(ProjectLine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<SerialUnit, $this> */
    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class);
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
            'dropship_confirmation_id' => 'integer',
            'company_id' => 'integer',
            'purchase_line_id' => 'integer',
            'project_line_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'qty_base' => 'decimal:3',
            'serial_unit_id' => 'integer',
            'is_replacement' => 'boolean',
            'correction_case_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
