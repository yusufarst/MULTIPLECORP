<?php

namespace App\Modules\INV\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What is restored, reclassified and substituted of a consumption or a drop-ship line (DATABASE §4.7
 * `stock_consumption_balances`, GB; C-27). It has no factory: a balance belongs to its posting service
 * (ARCHITECTURE §7, §19).
 *
 * @property int $id
 * @property int|null $stock_consumption_id
 * @property int|null $dropship_confirmation_line_id
 * @property string $qty_base
 * @property string $restored_qty_base
 * @property string $reclassified_qty_base
 * @property string $substituted_qty_base
 * @property CarbonImmutable $recorded_at
 */
class StockConsumptionBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<StockConsumption, $this> */
    public function consumption(): BelongsTo
    {
        return $this->belongsTo(StockConsumption::class, 'stock_consumption_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stock_consumption_id' => 'integer',
            'dropship_confirmation_line_id' => 'integer',
            'qty_base' => 'decimal:3',
            'restored_qty_base' => 'decimal:3',
            'reclassified_qty_base' => 'decimal:3',
            'substituted_qty_base' => 'decimal:3',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
