<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\InvoiceVersion;
use App\Modules\FIN\Models\InvoiceVersionLine;
use Database\Factories\PRJ\ProjectLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented itemized line of a DRAFT version: ten pieces of a demand line of the invoice's project at
 * Rp100.000.
 *
 * @extends Factory<InvoiceVersionLine>
 */
final class InvoiceVersionLineFactory extends Factory
{
    protected $model = InvoiceVersionLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_version_id' => InvoiceVersion::factory(),
            'company_id' => static fn (array $row): int => InvoiceVersion::query()->whereKey($row['invoice_version_id'])->firstOrFail()->company_id,
            'project_line_id' => static fn (array $row): int => ProjectLineFactory::newOn(InvoiceFactory::projectOf(InvoiceVersion::query()->whereKey($row['invoice_version_id'])->firstOrFail()->invoice_id)->id),
            'product_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['quantity_scale'],
            'description' => 'Barang contoh yang ditagih',
            'entered_qty' => '10.000',
            'entered_unit_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['entered_unit_id'],
            'ratio_to_base' => 1,
            'qty_base' => '10.000',
            'unit_price' => '100000.00',
            'line_sales_value' => '1000000.00',
        ];
    }
}
