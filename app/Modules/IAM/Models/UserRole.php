<?php

namespace App\Modules\IAM\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * An account's launch role (DATABASE §4.1 `user_roles`, SR), keyed by the pair; one per user in V1.
 *
 * @property int $user_id
 * @property int $role_id
 * @property int|null $granted_by
 * @property CarbonImmutable $granted_at
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 */
class UserRole extends Pivot
{
    protected $table = 'user_roles';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'granted_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
