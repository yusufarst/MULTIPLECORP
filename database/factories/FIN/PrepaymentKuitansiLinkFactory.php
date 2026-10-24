<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\PrepaymentKuitansiLink;
use Database\Factories\DOC\DocumentFactory;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented LINKED pre-payment Kuitansi of an invoice, settled by a payment of the invoice's company and
 * client.
 *
 * @extends Factory<PrepaymentKuitansiLink>
 */
final class PrepaymentKuitansiLinkFactory extends Factory
{
    protected $model = PrepaymentKuitansiLink::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $invoice = static fn (array $row): Invoice => Invoice::query()->whereKey($row['invoice_id'])->firstOrFail();

        return [
            'invoice_id' => Invoice::factory(),
            'company_id' => static fn (array $row): int => $invoice($row)->company_id,
            'document_id' => static fn (array $row): int => DocumentFactory::new()->createOne([
                'company_id' => $row['company_id'], 'document_type_code' => DocumentTypeFactory::ensure('DOC-05'), 'mode' => 'PRE_PAYMENT',
                'project_id' => null, 'invoice_id' => $row['invoice_id'],
            ])->id,
            'payment_id' => static fn (array $row): int => PaymentFactory::new()->createOne(['company_id' => $row['company_id'], 'client_organization_id' => $invoice($row)->client_organization_id])->id,
        ];
    }
}
