<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\Reservation;
use App\Modules\PRJ\Models\ProjectLine;
use Database\Factories\PRJ\ProjectLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented ACTIVE reservation of five pieces for a warehouse line, its line's company, product and scale.
 *
 * @extends Factory<Reservation>
 */
final class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_line_id' => ProjectLine::factory(),
            'company_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['company_id'],
            'product_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['quantity_scale'],
            'remaining_qty_base' => '5.000',
        ];
    }
}
