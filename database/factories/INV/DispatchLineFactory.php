<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\DispatchLine;
use App\Modules\PRJ\Models\ProjectLine;
use Database\Factories\PRJ\ProjectLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented unreserved dispatch of two pieces of a warehouse line, under a DISPATCH movement of its
 * project's company.
 *
 * @extends Factory<DispatchLine>
 */
final class DispatchLineFactory extends Factory
{
    protected $model = DispatchLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_line_id' => ProjectLine::factory(),
            'project_id' => static fn (array $row): int => ProjectLine::query()->whereKey($row['project_line_id'])->firstOrFail()->project_id,
            'company_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['company_id'],
            'product_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['quantity_scale'],
            'stock_movement_id' => static fn (array $row): int => StockMovementFactory::post('DISPATCH', $row['company_id'], $row['project_id']),
            'entered_qty' => '2.000',
            'entered_unit_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['entered_unit_id'],
            'ratio_to_base' => 1,
            'qty_base' => '2.000',
            'reserved_qty_base' => '0.000',
            'unreserved_qty_base' => '2.000',
        ];
    }
}
