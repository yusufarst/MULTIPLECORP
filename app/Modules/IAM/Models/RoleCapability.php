<?php

namespace App\Modules\IAM\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A capability a role holds (DATABASE §4.1 `role_capabilities`, CF), keyed by the pair.
 *
 * @property int $role_id
 * @property int $capability_id
 * @property CarbonImmutable $recorded_at
 */
class RoleCapability extends Pivot
{
    protected $table = 'role_capabilities';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
