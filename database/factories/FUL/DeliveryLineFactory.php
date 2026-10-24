<?php

namespace Database\Factories\FUL;

use App\Modules\FUL\Models\Delivery;
use App\Modules\FUL\Models\DeliveryLine;
use Database\Factories\INV\DispatchLineFactory;
use Database\Factories\PRJ\ProjectLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented delivery of the two pieces dispatched for a demand line of the delivery's project.
 *
 * @extends Factory<DeliveryLine>
 */
final class DeliveryLineFactory extends Factory
{
    protected $model = DeliveryLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'company_id' => static fn (array $row): int => Delivery::query()->whereKey($row['delivery_id'])->firstOrFail()->company_id,
            'project_line_id' => static fn (array $row): int => ProjectLineFactory::newOn(Delivery::query()->whereKey($row['delivery_id'])->firstOrFail()->project_id),
            'product_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['quantity_scale'],
            'dispatch_line_id' => static fn (array $row): int => DispatchLineFactory::new()->createOne(['project_line_id' => $row['project_line_id']])->id,
            'entered_qty' => '2.000',
            'entered_unit_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['entered_unit_id'],
            'ratio_to_base' => 1,
            'qty_base' => '2.000',
        ];
    }
}
