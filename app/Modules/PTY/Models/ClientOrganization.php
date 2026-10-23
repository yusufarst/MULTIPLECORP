<?php

namespace App\Modules\PTY\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PTY\ClientOrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client organization of the one generic hierarchy (DATABASE §4.3 `client_organizations`, MD; OB §8).
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $npwp
 * @property bool $is_active
 * @property CarbonImmutable|null $deactivated_at
 * @property int|null $deactivated_by
 * @property string|null $deactivated_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ClientOrganizationFactory::class)]
class ClientOrganization extends Model
{
    /** @use HasFactory<ClientOrganizationFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return HasMany<ClientUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ClientUnit::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
