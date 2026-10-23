<?php

namespace Database\Factories\PRJ;

use App\Modules\PRJ\Models\CommercialConfirmation;
use App\Modules\PRJ\Models\ConfirmationLinePin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented pin of ten pieces on a new line of the confirmation's project, at its price, with no tax.
 *
 * @extends Factory<ConfirmationLinePin>
 */
final class ConfirmationLinePinFactory extends Factory
{
    protected $model = ConfirmationLinePin::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'commercial_confirmation_id' => CommercialConfirmation::factory(),
            'project_line_id' => static fn (array $row): int => ProjectLineFactory::newOn(CommercialConfirmation::query()->whereKey($row['commercial_confirmation_id'])->firstOrFail()->project_id),
            'company_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['company_id'],
            'product_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['quantity_scale'],
            'description' => 'Barang contoh sesuai pesanan',
            'entered_qty' => '10.000',
            'entered_unit_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['entered_unit_id'],
            'ratio_to_base' => 1,
            'qty_base' => '10.000',
            'unit_price' => '150000.00',
            'sales_value' => '1500000.00',
            'tax_components' => [],
        ];
    }
}
