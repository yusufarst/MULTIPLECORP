<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\InvoiceBillingAct;
use App\Modules\IAM\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented root billing act of an invoice, due thirty days after it is billed.
 *
 * @extends Factory<InvoiceBillingAct>
 */
final class InvoiceBillingActFactory extends Factory
{
    protected $model = InvoiceBillingAct::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'billed_at' => '2026-10-06',
            'due_date' => '2026-11-05',
            'kind' => 'BILLING',
            'decided_by' => User::factory(),
        ];
    }
}
