<?php

namespace App\Modules\PRJ\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-line conservation guard of a project line (DATABASE §4.5 `project_line_balances`, GB; §9):
 * posted only by DemandPosting with its facts (C-05, C-30). It has no factory: a balance belongs to its
 * posting service (ARCHITECTURE §7, §19), and a test writes the row as the posting would.
 *
 * @property int $project_line_id
 * @property string $fulfillment_mode
 * @property string $demand_qty_base
 * @property string $reserved_qty_base
 * @property string $dispatched_net_qty_base
 * @property string $dropship_confirmed_net_qty_base
 * @property string $handed_over_qty_base
 * @property string $delivered_net_qty_base
 * @property string $cancelled_qty_base
 * @property CarbonImmutable $recorded_at
 */
class ProjectLineBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $primaryKey = 'project_line_id';

    public $incrementing = false;

    /** @return BelongsTo<ProjectLine, $this> */
    public function projectLine(): BelongsTo
    {
        return $this->belongsTo(ProjectLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'project_line_id' => 'integer',
            'demand_qty_base' => 'decimal:3',
            'reserved_qty_base' => 'decimal:3',
            'dispatched_net_qty_base' => 'decimal:3',
            'dropship_confirmed_net_qty_base' => 'decimal:3',
            'handed_over_qty_base' => 'decimal:3',
            'delivered_net_qty_base' => 'decimal:3',
            'cancelled_qty_base' => 'decimal:3',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
