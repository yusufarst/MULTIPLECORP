<?php

namespace App\Modules\ORG\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ORG\TaxRateVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An effective-dated configured rate (DATABASE §4.2 `tax_rate_versions`, CF; DIR-024 D-1 item 3): the rate
 * in percent with six places, carried as an exact decimal string, never a float (§17).
 *
 * @property int $id
 * @property int $tax_type_id
 * @property string $rate_percent
 * @property int $base_numerator
 * @property int $base_denominator
 * @property string $rounding_mode
 * @property string $effective
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(TaxRateVersionFactory::class)]
class TaxRateVersion extends Model
{
    /** @use HasFactory<TaxRateVersionFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<TaxType, $this> */
    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tax_type_id' => 'integer',
            'rate_percent' => 'decimal:6',
            'base_numerator' => 'integer',
            'base_denominator' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
