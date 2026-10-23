<?php

namespace App\Modules\CST\Models;

use App\Modules\PUR\Models\PurchaseCharge;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A charge's conservation (DATABASE §4.8 `purchase_charge_balances`, GB): allocated, pending or expensed,
 * never both (AX-29; L-47; C-21). It has no factory: a balance belongs to its posting service
 * (ARCHITECTURE §7, §19).
 *
 * @property int $purchase_charge_id
 * @property string $amount
 * @property string $allocated_amount
 * @property string $expensed_amount
 * @property CarbonImmutable $recorded_at
 */
class PurchaseChargeBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $primaryKey = 'purchase_charge_id';

    public $incrementing = false;

    /** @return BelongsTo<PurchaseCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(PurchaseCharge::class, 'purchase_charge_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_charge_id' => 'integer',
            'amount' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
            'expensed_amount' => 'decimal:2',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
