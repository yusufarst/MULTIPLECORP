<?php

namespace App\Modules\ORG\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ORG\TaxTypeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tax kind as data, never a code constant (DATABASE §4.2 `tax_types`, CF; BR-FIN-17; FS-13).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property bool $is_active
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(TaxTypeFactory::class)]
class TaxType extends Model
{
    /** @use HasFactory<TaxTypeFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return HasMany<TaxRateVersion, $this> */
    public function rateVersions(): HasMany
    {
        return $this->hasMany(TaxRateVersion::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
