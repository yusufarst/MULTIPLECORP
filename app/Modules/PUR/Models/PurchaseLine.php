<?php

namespace App\Modules\PUR\Models;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use App\Modules\PRJ\Models\ProjectLine;
use Carbon\CarbonImmutable;
use Database\Factories\PUR\PurchaseLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A purchased quantity at its actual price (DATABASE §4.6 `purchase_lines`, AF, draft-editable before the
 * first effect; L-23): edited only before any receipt, confirmation or disbursement, afterwards adjusted.
 *
 * @property int $id
 * @property int $purchase_id
 * @property int $company_id
 * @property int $line_no
 * @property string $kind
 * @property int $product_id
 * @property int $quantity_scale
 * @property int|null $project_line_id
 * @property string $delivery_mode
 * @property string $entered_qty
 * @property int $entered_unit_id
 * @property int $ratio_to_base
 * @property string $qty_base
 * @property string $unit_price
 * @property string $line_value
 * @property string $description
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(PurchaseLineFactory::class)]
class PurchaseLine extends Model
{
    /** @use HasFactory<PurchaseLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProjectLine, $this> */
    public function projectLine(): BelongsTo
    {
        return $this->belongsTo(ProjectLine::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<PurchaseLineTaxComponent, $this> */
    public function taxComponents(): HasMany
    {
        return $this->hasMany(PurchaseLineTaxComponent::class);
    }

    /** @return HasOne<PurchaseLineBalance, $this> */
    public function balance(): HasOne
    {
        return $this->hasOne(PurchaseLineBalance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_id' => 'integer',
            'company_id' => 'integer',
            'line_no' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'project_line_id' => 'integer',
            'entered_qty' => 'decimal:3',
            'entered_unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'qty_base' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_value' => 'decimal:2',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
