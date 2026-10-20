<?php

namespace App\Modules\CAT\Models;

use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\CAT\ProductPriceHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A default price and its history (DATABASE §4.4 `product_price_history`, AF; CAP-03): the latest row of a
 * kind is the current default; the amount is an exact decimal string (§17).
 *
 * @property int $id
 * @property int $product_id
 * @property string $price_kind
 * @property string $amount
 * @property CarbonImmutable $effective_from
 * @property string|null $reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(ProductPriceHistoryFactory::class)]
class ProductPriceHistory extends Model
{
    use AppendOnly;

    /** @use HasFactory<ProductPriceHistoryFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $table = 'product_price_history';

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
            'amount' => 'decimal:2',
            'effective_from' => 'immutable_date',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
