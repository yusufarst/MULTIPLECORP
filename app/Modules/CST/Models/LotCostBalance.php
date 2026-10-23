<?php

namespace App\Modules\CST\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A lot's current net cost (DATABASE §4.8 `lot_cost_balances`, GB): the zeroing draw takes exactly the
 * remaining cost (BR-FIN-13; C-26). It has no factory: a balance belongs to its posting service
 * (ARCHITECTURE §7, §19); the lot is INV's (TASK-008).
 *
 * @property int $lot_id
 * @property string $remaining_qty_base
 * @property string $remaining_cost
 * @property CarbonImmutable $recorded_at
 */
class LotCostBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $primaryKey = 'lot_id';

    public $incrementing = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lot_id' => 'integer',
            'remaining_qty_base' => 'decimal:3',
            'remaining_cost' => 'decimal:2',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
