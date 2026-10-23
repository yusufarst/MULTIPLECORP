<?php

namespace App\Modules\INV\Models;

use Carbon\CarbonImmutable;
use Database\Factories\INV\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A rack or location of the one warehouse pool (DATABASE §4.7 `locations`, MD; REF-023), never deleted
 * once referenced.
 *
 * @property int $id
 * @property string $code
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
#[UseFactory(LocationFactory::class)]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
            'deactivated_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
