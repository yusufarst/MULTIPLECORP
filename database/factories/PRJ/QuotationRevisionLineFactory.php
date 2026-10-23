<?php

namespace Database\Factories\PRJ;

use App\Modules\PRJ\Models\QuotationRevision;
use App\Modules\PRJ\Models\QuotationRevisionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented quoted line: ten pieces of a new line of the revision's project, at its price, with no tax.
 *
 * @extends Factory<QuotationRevisionLine>
 */
final class QuotationRevisionLineFactory extends Factory
{
    protected $model = QuotationRevisionLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'quotation_revision_id' => QuotationRevision::factory(),
            'project_line_id' => static fn (array $row): int => ProjectLineFactory::newOn(QuotationRevision::query()->whereKey($row['quotation_revision_id'])->firstOrFail()->project_id),
            'company_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['company_id'],
            'product_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['quantity_scale'],
            'description' => 'Barang contoh sesuai penawaran',
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
