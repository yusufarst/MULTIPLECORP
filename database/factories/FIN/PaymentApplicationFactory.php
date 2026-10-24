<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Models\PaymentApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented ACTIVE application of Rp500.000 of a payment to an invoice of the same company and client.
 *
 * @extends Factory<PaymentApplication>
 */
final class PaymentApplicationFactory extends Factory
{
    protected $model = PaymentApplication::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $payment = static fn (array $row): Payment => Payment::query()->whereKey($row['payment_id'])->firstOrFail();

        return [
            'payment_id' => Payment::factory(),
            'company_id' => static fn (array $row): int => $payment($row)->company_id,
            'client_organization_id' => static fn (array $row): int => $payment($row)->client_organization_id,
            'invoice_id' => static fn (array $row): int => InvoiceFactory::of($row['company_id'], $row['client_organization_id'])->id,
            'target_kind' => 'INVOICE',
            'amount' => '500000.00',
            'business_date' => '2026-10-07',
        ];
    }
}
