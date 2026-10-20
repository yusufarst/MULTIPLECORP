<?php

namespace App\Modules\IAM\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IAM\CapabilityFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A capability of PERMISSIONS_MATRIX's catalogue (DATABASE §4.1 `capabilities`, CF) with its authority
 * class — ADM, ADM_PLUS or OWNER_ONLY (C-40).
 *
 * @property int $id
 * @property string $code
 * @property string $authority_class
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(CapabilityFactory::class)]
class Capability extends Model
{
    /** @use HasFactory<CapabilityFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsToMany<Role, $this, RoleCapability> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, RoleCapability::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
