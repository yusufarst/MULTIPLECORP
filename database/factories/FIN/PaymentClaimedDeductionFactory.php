<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Models\PaymentClaimedDeduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented remittance advice of a Rp15.000 transfer fee deducted from a payment.
 *
 * @extends Factory<PaymentClaimedDeduction>
 */
final class PaymentClaimedDeductionFactory extends Factory
{
    protected $model = PaymentClaimedDeduction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'deduction_class' => 'FEE',
            'fee_type' => 'BIAYA_TRANSFER',
            'claimed_amount' => '15000.00',
        ];
    }
}
