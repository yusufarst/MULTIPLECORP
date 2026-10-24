<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\InvoiceVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented first DRAFT version of an invoice: Rp1.000.000 of sales and Rp110.000 of output tax, the
 * client as an invented snapshot.
 *
 * @extends Factory<InvoiceVersion>
 */
final class InvoiceVersionFactory extends Factory
{
    protected $model = InvoiceVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'company_id' => static fn (array $row): int => Invoice::query()->whereKey($row['invoice_id'])->firstOrFail()->company_id,
            'version_no' => 1,
            'kind' => 'ITEMIZED',
            'business_date' => '2026-10-05',
            'sales_value' => '1000000.00',
            'output_tax_value' => '110000.00',
            'client_snapshot' => ['nama' => 'SMA Contoh Makmur', 'unit' => 'Tata Usaha', 'alamat' => 'Jl. Contoh No. 1', 'pic' => 'Ibu Contoh'],
            'revision_basis' => 'INITIAL',
        ];
    }
}
