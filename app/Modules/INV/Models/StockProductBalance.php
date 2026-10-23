<?php

namespace App\Modules\INV\Models;

use App\Modules\CAT\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product's guard (DATABASE §4.7 `stock_product_balances`, GB; §5.2): AVAILABLE = ON HAND − RESERVED
 * − UNUSABLE ≥ 0 at every commit (C-04). It has no factory: a balance belongs to its posting service
 * (ARCHITECTURE §7, §19).
 *
 * @property int $product_id
 * @property string $on_hand_qty
 * @property string $unusable_qty
 * @property string $reserved_qty
 * @property int $version
 * @property CarbonImmutable $recorded_at
 */
class StockProductBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'on_hand_qty' => 'decimal:3',
            'unusable_qty' => 'decimal:3',
            'reserved_qty' => 'decimal:3',
            'version' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
