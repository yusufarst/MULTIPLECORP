<?php

namespace App\Modules\CAT\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CAT\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A product or service of the catalogue (DATABASE §4.4 `products`, MD; §7): its base unit is the smallest
 * counted unit and its base quantities hold its scale, 0 for counted and serialized goods; quantities are
 * exact decimal strings, never floats (§17).
 *
 * @property int $id
 * @property string $public_id
 * @property string $kind
 * @property string|null $sku
 * @property string $name
 * @property int $base_unit_id
 * @property int $quantity_scale
 * @property bool $is_serialized
 * @property string|null $min_stock_qty_base
 * @property bool $is_active
 * @property CarbonImmutable|null $deactivated_at
 * @property int|null $deactivated_by
 * @property string|null $deactivated_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Unit, $this> */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /** @return HasMany<ProductUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /** @return HasMany<ProductBarcode, $this> */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /** @return HasMany<ProductPriceHistory, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'base_unit_id' => 'integer',
            'quantity_scale' => 'integer',
            'is_serialized' => 'boolean',
            'min_stock_qty_base' => 'decimal:3',
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
