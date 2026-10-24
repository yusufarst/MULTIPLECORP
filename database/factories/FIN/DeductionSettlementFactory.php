<?php

namespace Database\Factories\FIN;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\FIN\Models\DeductionSettlement;
use App\Modules\FIN\Models\Payment;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented ACTIVE settlement of a Rp15.000 transfer fee, anchored to a payment, an invoice of the same
 * company and client and the fee advice that proves it.
 *
 * @extends Factory<DeductionSettlement>
 */
final class DeductionSettlementFactory extends Factory
{
    protected $model = DeductionSettlement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $payment = static fn (array $row): Payment => Payment::query()->whereKey($row['payment_id'])->firstOrFail();

        return [
            'payment_id' => Payment::factory(),
            'company_id' => static fn (array $row): int => $payment($row)->company_id,
            'client_organization_id' => static fn (array $row): int => $payment($row)->client_organization_id,
            'invoice_id' => static fn (array $row): int => InvoiceFactory::of($row['company_id'], $row['client_organization_id'])->id,
            'settlement_class' => 'FEE',
            'fee_type' => 'BIAYA_TRANSFER',
            'specific_type_key' => 'BIAYA_TRANSFER',
            'amount' => '15000.00',
            'evidence_document_id' => static fn (array $row): int => self::evidence($row['company_id'], 'FEE_ADVICE')->id,
            'evidence_type' => 'FEE_ADVICE',
            'business_date' => '2026-10-07',
        ];
    }

    /** An invented proof of the type, of the company. */
    public static function evidence(mixed $company, string $type): EvidenceDocument
    {
        return EvidenceDocumentFactory::new()->createOne(['company_id' => $company, 'evidence_type' => $type]);
    }
}
