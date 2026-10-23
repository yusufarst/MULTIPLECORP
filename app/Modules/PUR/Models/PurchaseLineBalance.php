<?php

namespace App\Modules\PUR\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purchase line's receipt progress, remainder and cumulative cost posting (DATABASE §4.6
 * `purchase_line_balances`, GB; CALC-03; §6.1): two separate quantity caps (C-29) and the posted-value
 * cap (C-57). It has no factory: a balance belongs to its posting service (ARCHITECTURE §7, §19).
 *
 * @property int $purchase_line_id
 * @property string $ordered_qty_base
 * @property string $normal_received_net_qty_base
 * @property string $closed_qty_base
 * @property string $dropship_confirmed_net_qty_base
 * @property string $replacement_expected_qty_base
 * @property string $replacement_received_net_qty_base
 * @property string $goods_value
 * @property string $noncreditable_tax_value
 * @property string $posted_goods_value
 * @property string $posted_noncreditable_tax_value
 * @property CarbonImmutable $recorded_at
 */
class PurchaseLineBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $primaryKey = 'purchase_line_id';

    public $incrementing = false;

    /** @return BelongsTo<PurchaseLine, $this> */
    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_line_id' => 'integer',
            'ordered_qty_base' => 'decimal:3',
            'normal_received_net_qty_base' => 'decimal:3',
            'closed_qty_base' => 'decimal:3',
            'dropship_confirmed_net_qty_base' => 'decimal:3',
            'replacement_expected_qty_base' => 'decimal:3',
            'replacement_received_net_qty_base' => 'decimal:3',
            'goods_value' => 'decimal:2',
            'noncreditable_tax_value' => 'decimal:2',
            'posted_goods_value' => 'decimal:2',
            'posted_noncreditable_tax_value' => 'decimal:2',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
