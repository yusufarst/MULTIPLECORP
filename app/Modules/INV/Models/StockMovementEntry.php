<?php

namespace App\Modules\INV\Models;

use App\Modules\CAT\Models\Product;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\StockMovementEntryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry of the immutable stock ledger (DATABASE §4.7 `stock_movement_entries`, AF; §5.3), signed by
 * its movement's type (C-07), on its lot or — for a pending case — its case.
 *
 * @property int $id
 * @property int $stock_movement_id
 * @property string $movement_type
 * @property int $product_id
 * @property int $quantity_scale
 * @property int $location_id
 * @property string $condition
 * @property int|null $lot_id
 * @property int|null $unattributed_case_id
 * @property int|null $serial_unit_id
 * @property string $qty_delta_base
 * @property int|null $stock_consumption_id
 * @property int|null $stock_restoration_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(StockMovementEntryFactory::class)]
class StockMovementEntry extends Model
{
    use AppendOnly;

    /** @use HasFactory<StockMovementEntryFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<StockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<InventoryLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    /** @return BelongsTo<UnattributedLossCase, $this> */
    public function unattributedCase(): BelongsTo
    {
        return $this->belongsTo(UnattributedLossCase::class);
    }

    /** @return BelongsTo<SerialUnit, $this> */
    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class);
    }

    /** @return BelongsTo<StockConsumption, $this> */
    public function consumption(): BelongsTo
    {
        return $this->belongsTo(StockConsumption::class, 'stock_consumption_id');
    }

    /** @return BelongsTo<StockRestoration, $this> */
    public function restoration(): BelongsTo
    {
        return $this->belongsTo(StockRestoration::class, 'stock_restoration_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stock_movement_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'location_id' => 'integer',
            'lot_id' => 'integer',
            'unattributed_case_id' => 'integer',
            'serial_unit_id' => 'integer',
            'qty_delta_base' => 'decimal:3',
            'stock_consumption_id' => 'integer',
            'stock_restoration_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
