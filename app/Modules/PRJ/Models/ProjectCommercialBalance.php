<?php

namespace App\Modules\PRJ\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project's invoice cap (DATABASE §4.5 `project_commercial_balances`, GB; L-16; AX-16): never more
 * invoiced than confirmed (C-31). It has no factory: a balance belongs to its posting service
 * (ARCHITECTURE §7, §19).
 *
 * @property int $project_id
 * @property string $confirmed_sales_value
 * @property string $invoiced_sales_value
 * @property CarbonImmutable $recorded_at
 */
class ProjectCommercialBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $primaryKey = 'project_id';

    public $incrementing = false;

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'confirmed_sales_value' => 'decimal:2',
            'invoiced_sales_value' => 'decimal:2',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
