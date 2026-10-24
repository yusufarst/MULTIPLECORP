<?php

namespace App\Modules\FUL\Models;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use App\Modules\INV\Models\DispatchLine;
use App\Modules\PRJ\Models\ProjectLine;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FUL\DeliveryLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A demand line's quantity handed over from one dispatch or drop-ship line (DATABASE §4.9
 * `delivery_lines`, AF; AX-09), reversed once and exactly on an entry error (CM-08; C-30).
 *
 * @property int $id
 * @property int $delivery_id
 * @property int $company_id
 * @property int $project_line_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property int|null $dispatch_line_id
 * @property int|null $dropship_confirmation_line_id
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property int $ratio_to_base
 * @property string $qty_base
 * @property int|null $reverses_delivery_line_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(DeliveryLineFactory::class)]
class DeliveryLine extends Model
{
    use AppendOnly;

    /** @use HasFactory<DeliveryLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Delivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
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

    /** @return BelongsTo<DispatchLine, $this> */
    public function dispatchLine(): BelongsTo
    {
        return $this->belongsTo(DispatchLine::class);
    }

    /** @return BelongsTo<DropshipConfirmationLine, $this> */
    public function dropshipLine(): BelongsTo
    {
        return $this->belongsTo(DropshipConfirmationLine::class, 'dropship_confirmation_line_id');
    }

    /** @return BelongsTo<Unit, $this> */
    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<DeliveryLine, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(DeliveryLine::class, 'reverses_delivery_line_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'delivery_id' => 'integer',
            'company_id' => 'integer',
            'project_line_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'dispatch_line_id' => 'integer',
            'dropship_confirmation_line_id' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'qty_base' => 'decimal:3',
            'reverses_delivery_line_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
