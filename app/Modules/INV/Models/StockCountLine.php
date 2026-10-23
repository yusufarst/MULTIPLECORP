<?php

namespace App\Modules\INV\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\StockCountLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An observation within a scope a count opened (DATABASE §4.7 `stock_count_lines`, AF; WF-INV-05).
 *
 * @property int $id
 * @property int $stock_count_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property int $location_id
 * @property string $condition
 * @property string $counted_qty
 * @property int|null $serial_unit_id
 * @property string|null $unregistered_serial_text
 * @property int $counted_by
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(StockCountLineFactory::class)]
class StockCountLine extends Model
{
    use AppendOnly;

    /** @use HasFactory<StockCountLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<StockCount, $this> */
    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    /** @return BelongsTo<SerialUnit, $this> */
    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stock_count_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'location_id' => 'integer',
            'counted_qty' => 'decimal:3',
            'serial_unit_id' => 'integer',
            'counted_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
