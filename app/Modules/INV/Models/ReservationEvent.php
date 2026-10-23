<?php

namespace App\Modules\INV\Models;

use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\ReservationEventFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reservation's history (DATABASE §4.7 `reservation_events`, AF; §5.5): a CUT names the
 * supply-reducing movement (SF-RSV-CUT; C-06).
 *
 * @property int $id
 * @property int $reservation_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property string $kind
 * @property string $qty_delta_base
 * @property string|null $reason
 * @property int|null $dispatch_line_id
 * @property int|null $cut_by_movement_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(ReservationEventFactory::class)]
class ReservationEvent extends Model
{
    use AppendOnly;

    /** @use HasFactory<ReservationEventFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<DispatchLine, $this> */
    public function dispatchLine(): BelongsTo
    {
        return $this->belongsTo(DispatchLine::class);
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function cutByMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reservation_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'qty_delta_base' => 'decimal:3',
            'dispatch_line_id' => 'integer',
            'cut_by_movement_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
