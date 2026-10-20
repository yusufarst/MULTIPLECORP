<?php

namespace App\Modules\IAM\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IAM\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An individual account (DATABASE §4.1 `users`, MD; BR-XC-02): never deleted, deactivated instead.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property CarbonImmutable|null $deactivated_at
 * @property int|null $deactivated_by
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_step
 * @property string|null $locale
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    /** No remember-me token: a session ends when the browser closes (SECURITY AU-06). */
    protected $rememberTokenName = '';

    /** The credentials are never serialized (SECURITY AU-21; DATABASE §28). */
    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsToMany<Role, $this, UserRole> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, UserRole::class)->withPivot('granted_by', 'granted_at');
    }

    /** @return HasMany<UserCapabilityGrant, $this> */
    public function capabilityGrants(): HasMany
    {
        return $this->hasMany(UserCapabilityGrant::class);
    }

    /** @return HasMany<UserCompanyGrant, $this> */
    public function companyGrants(): HasMany
    {
        return $this->hasMany(UserCompanyGrant::class);
    }

    /** @return HasMany<UserExternalIdentity, $this> */
    public function externalIdentities(): HasMany
    {
        return $this->hasMany(UserExternalIdentity::class);
    }

    /** @return BelongsTo<User, $this> */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'two_factor_last_step' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
