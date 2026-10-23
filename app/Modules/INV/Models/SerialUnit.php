<?php

namespace App\Modules\INV\Models;

use App\Modules\CAT\Models\Product;
use Carbon\CarbonImmutable;
use Database\Factories\INV\SerialUnitFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One physical identity per product and normalized serial (DATABASE §4.7 `serial_units`, SR; §8; C-02),
 * in stock exactly when it has a lot, location and condition, never twice.
 *
 * @property int $id
 * @property int $product_id
 * @property string $serial_number
 * @property string $serial_key
 * @property string $state
 * @property int|null $current_lot_id
 * @property int|null $current_location_id
 * @property string|null $current_condition
 * @property int|null $last_entry_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(SerialUnitFactory::class)]
class SerialUnit extends Model
{
    /** @use HasFactory<SerialUnitFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<InventoryLot, $this> */
    public function currentLot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<StockMovementEntry, $this> */
    public function lastEntry(): BelongsTo
    {
        return $this->belongsTo(StockMovementEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'current_lot_id' => 'integer',
            'current_location_id' => 'integer',
            'last_entry_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
