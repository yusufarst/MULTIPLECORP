<?php

namespace App\Modules\PTY\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PTY\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A supplier's master identity (DATABASE §4.3 `suppliers`, MD): its prices are derived per company from
 * purchases, never stored (BR-ACC-02).
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $npwp
 * @property string|null $address_lines
 * @property string|null $city
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
#[UseFactory(SupplierFactory::class)]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
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
