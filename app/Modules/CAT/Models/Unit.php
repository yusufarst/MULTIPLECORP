<?php

namespace App\Modules\CAT\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CAT\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A unit of the dictionary (DATABASE §4.4 `units`, MD), inactive instead of deleted once referenced (§19).
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
#[UseFactory(UnitFactory::class)]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

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
