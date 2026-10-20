<?php

namespace App\Modules\IAM\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IAM\UserCapabilityGrantFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A separately granted ADM+ authority (DATABASE §4.1 `user_capability_grants`, SR; DIR-024 D-5):
 * revoked, never deleted.
 *
 * @property int $id
 * @property int $user_id
 * @property int $capability_id
 * @property int|null $granted_by
 * @property CarbonImmutable $granted_at
 * @property int|null $revoked_by
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(UserCapabilityGrantFactory::class)]
class UserCapabilityGrant extends Model
{
    /** @use HasFactory<UserCapabilityGrantFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Capability, $this> */
    public function capability(): BelongsTo
    {
        return $this->belongsTo(Capability::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'granted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
