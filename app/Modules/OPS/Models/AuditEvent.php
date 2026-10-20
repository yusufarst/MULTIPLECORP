<?php

namespace App\Modules\OPS\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\AuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business audit event (DATABASE §4.13 `audit_events`, AE; §23; SECURITY LG-01): written in the
 * transaction of the change it records, never changed afterwards (C-53).
 *
 * @property int $id
 * @property CarbonImmutable $occurred_at
 * @property int|null $actor_user_id
 * @property int|null $company_id
 * @property string $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string|null $entity_public_id
 * @property string|null $reason
 * @property string|null $correlation_id
 * @property string|null $command_id
 * @property string $authority_class
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(AuditEventFactory::class)]
class AuditEvent extends Model
{
    use AppendOnly;

    /** @use HasFactory<AuditEventFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

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
            'company_id' => 'integer',
            'entity_id' => 'integer',
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
