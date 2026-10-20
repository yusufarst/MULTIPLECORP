<?php

namespace App\Modules\PTY\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PTY\ClientUnitFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A unit or department of a client organization (DATABASE §4.3 `client_units`, MD).
 *
 * @property int $id
 * @property int $client_organization_id
 * @property string $name
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
#[UseFactory(ClientUnitFactory::class)]
class ClientUnit extends Model
{
    /** @use HasFactory<ClientUnitFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<ClientOrganization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(ClientOrganization::class, 'client_organization_id');
    }

    /** @return HasMany<ClientAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }

    /** @return HasMany<ClientContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'client_organization_id' => 'integer',
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
