<?php

namespace App\Modules\OPS\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\SecurityEventFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A security event (DATABASE §4.13 `security_events`, AE; SECURITY LG-02), kept apart from the business
 * audit and never changed afterwards (C-53).
 *
 * @property int $id
 * @property CarbonImmutable $occurred_at
 * @property string $event_kind
 * @property int|null $user_id
 * @property int|null $actor_user_id
 * @property string|null $reason_code
 * @property string|null $route_name
 * @property string|null $target_public_id
 * @property string|null $identifier_hash
 * @property string|null $ip_hash
 * @property string|null $user_agent_summary
 * @property string|null $correlation_id
 * @property array<string, mixed>|null $details
 * @property string|null $command_id
 * @property string|null $target_entity_type
 * @property string|null $related_entity_type
 * @property string|null $related_public_id
 * @property int|null $company_id
 * @property int|null $related_company_id
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(SecurityEventFactory::class)]
class SecurityEvent extends Model
{
    use AppendOnly;

    /** @use HasFactory<SecurityEventFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'details' => 'array',
            'company_id' => 'integer',
            'related_company_id' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
