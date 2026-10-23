<?php

namespace App\Modules\INV\Models;

use App\Support\Database\IntegerList;
use Carbon\CarbonImmutable;
use Database\Factories\INV\StockCountFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An opname session over a scope (DATABASE §4.7 `stock_counts`, SR; §5.6), its typed basis written when
 * it opens; a movement in its scope makes it STALE (L-14; C-33).
 *
 * @property int $id
 * @property string $public_id
 * @property int $location_id
 * @property string $condition
 * @property list<int>|null $product_ids
 * @property string $state
 * @property CarbonImmutable $started_at
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(StockCountFactory::class)]
class StockCount extends Model
{
    /** @use HasFactory<StockCountFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<StockCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    /** @return HasMany<StockCountFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(StockCountFinding::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'location_id' => 'integer',
            'product_ids' => IntegerList::class,
            'started_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
