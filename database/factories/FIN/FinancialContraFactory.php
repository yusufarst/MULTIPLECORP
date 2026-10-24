<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\FinancialContra;
use App\Modules\FIN\Models\Payment;
use App\Modules\IAM\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented Rp100.000 contra of a payment recorded in excess, recorded by an invented user.
 *
 * @extends Factory<FinancialContra>
 */
final class FinancialContraFactory extends Factory
{
    protected $model = FinancialContra::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'company_id' => static fn (array $row): int => Payment::query()->whereKey($row['payment_id'])->firstOrFail()->company_id,
            'amount' => '100000.00',
            'reason' => 'Pembayaran tercatat melebihi mutasi bank',
            'business_date' => '2026-10-10',
            'recorded_by' => User::factory(),
        ];
    }
}
