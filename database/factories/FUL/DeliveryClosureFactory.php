<?php

namespace Database\Factories\FUL;

use App\Modules\FUL\Models\DeliveryClosure;
use App\Modules\IAM\Models\User;
use App\Modules\INV\Models\DispatchLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented closure of one dispatched piece lost in transit, decided by an invented user.
 *
 * @extends Factory<DeliveryClosure>
 */
final class DeliveryClosureFactory extends Factory
{
    protected $model = DeliveryClosure::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $dispatch = static fn (array $row): DispatchLine => DispatchLine::query()->whereKey($row['dispatch_line_id'])->firstOrFail();

        return [
            'dispatch_line_id' => DispatchLine::factory(),
            'project_line_id' => static fn (array $row): int => $dispatch($row)->project_line_id,
            'company_id' => static fn (array $row): int => $dispatch($row)->company_id,
            'product_id' => static fn (array $row): int => $dispatch($row)->product_id,
            'quantity_scale' => static fn (array $row): int => $dispatch($row)->quantity_scale,
            'closed_qty_base' => '1.000',
            'closure_kind' => 'LOST_IN_TRANSIT',
            'reason' => 'Barang hilang dalam pengiriman ekspedisi',
            'decided_by' => User::factory(),
            'business_date' => '2026-10-04',
        ];
    }
}
