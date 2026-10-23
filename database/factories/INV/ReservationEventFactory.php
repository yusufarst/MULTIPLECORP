<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\Reservation;
use App\Modules\INV\Models\ReservationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The invented creation event of a reservation of five pieces.
 *
 * @extends Factory<ReservationEvent>
 */
final class ReservationEventFactory extends Factory
{
    protected $model = ReservationEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'product_id' => static fn (array $row): int => Reservation::query()->whereKey($row['reservation_id'])->firstOrFail()->product_id,
            'quantity_scale' => static fn (array $row): int => Reservation::query()->whereKey($row['reservation_id'])->firstOrFail()->quantity_scale,
            'kind' => 'CREATE',
            'qty_delta_base' => '5.000',
        ];
    }
}
