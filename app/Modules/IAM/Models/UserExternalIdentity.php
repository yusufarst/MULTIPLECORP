<?php

namespace App\Modules\IAM\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IAM\UserExternalIdentityFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Google sign-in link (DATABASE §4.1 `user_external_identities`, SR; SECURITY AU-22–AU-24): found only
 * through its provider subject; unlinked once, never deleted, and relinking adds a row (C-63, C-64).
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_subject
 * @property string|null $email_at_link
 * @property CarbonImmutable $linked_at
 * @property int $linked_by
 * @property CarbonImmutable|null $unlinked_at
 * @property int|null $unlinked_by
 * @property string|null $unlink_reason
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(UserExternalIdentityFactory::class)]
class UserExternalIdentity extends Model
{
    /** @use HasFactory<UserExternalIdentityFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    /** @return BelongsTo<User, $this> */
    public function unlinkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlinked_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'linked_at' => 'immutable_datetime',
            'unlinked_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
