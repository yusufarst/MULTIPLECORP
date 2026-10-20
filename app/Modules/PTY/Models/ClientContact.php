<?php

namespace App\Modules\PTY\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PTY\ClientContactFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A unit's person in charge (DATABASE §4.3 `client_contacts`, MD), snapshotted as used (§14).
 *
 * @property int $id
 * @property int $client_unit_id
 * @property string $name
 * @property string|null $position
 * @property string|null $phone
 * @property string|null $email
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
#[UseFactory(ClientContactFactory::class)]
class ClientContact extends Model
{
    /** @use HasFactory<ClientContactFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<ClientUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class, 'client_unit_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'client_unit_id' => 'integer',
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
