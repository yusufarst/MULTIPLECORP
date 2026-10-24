<?php

namespace Database\Factories\FUL;

use App\Modules\FUL\Models\ServiceHandover;
use App\Modules\FUL\Models\ServiceHandoverLine;
use Database\Factories\PRJ\ProjectLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented handover of one unit of a demand line of the handover's project.
 *
 * @extends Factory<ServiceHandoverLine>
 */
final class ServiceHandoverLineFactory extends Factory
{
    protected $model = ServiceHandoverLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_handover_id' => ServiceHandover::factory(),
            'company_id' => static fn (array $row): int => ServiceHandover::query()->whereKey($row['service_handover_id'])->firstOrFail()->company_id,
            'project_line_id' => static fn (array $row): int => ProjectLineFactory::newOn(ServiceHandover::query()->whereKey($row['service_handover_id'])->firstOrFail()->project_id),
            'product_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['quantity_scale'],
            'entered_qty' => '1.000',
            'entered_unit_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['entered_unit_id'],
            'ratio_to_base' => 1,
            'qty_base' => '1.000',
            'scope_note' => 'Pemasangan dan uji fungsi',
        ];
    }
}
