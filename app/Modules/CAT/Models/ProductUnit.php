<?php

namespace App\Modules\CAT\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CAT\ProductUnitFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product's unit (DATABASE §4.4 `product_units`, MD; §7): the base at ratio 1, alternates as exact integer
 * multiples (L-31).
 *
 * @property int $id
 * @property int $product_id
 * @property int $unit_id
 * @property int $ratio_to_base
 * @property bool $is_base
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
#[UseFactory(ProductUnitFactory::class)]
class ProductUnit extends Model
{
    /** @use HasFactory<ProductUnitFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'ratio_to_base' => 'integer',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
