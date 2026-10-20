<?php

namespace App\Modules\IAM\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IAM\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A role (DATABASE §4.1 `roles`, CF): the two built-in launch roles, custom roles later (D-01).
 * Authorization reads a role's capabilities, never its code (SECURITY AZ-02).
 *
 * @property int $id
 * @property string $code
 * @property bool $is_builtin
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(RoleFactory::class)]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsToMany<Capability, $this, RoleCapability> */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Capability::class, RoleCapability::class);
    }

    /** @return BelongsToMany<User, $this, UserRole> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, UserRole::class)->withPivot('granted_by', 'granted_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
