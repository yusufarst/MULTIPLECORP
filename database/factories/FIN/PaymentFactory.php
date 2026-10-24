<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\Payment;
use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\ClientOrganization;
use Database\Factories\ORG\CompanyBankAccountFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented transfer of Rp1.500.000 from a client into its company's own account, not yet cited by a
 * statement line.
 *
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_organization_id' => ClientOrganization::factory(),
            'business_date' => '2026-10-07',
            'amount' => '1500000.00',
            'method' => 'TRANSFER',
            'company_bank_account_id' => static fn (array $row): int => CompanyBankAccountFactory::new()->createOne(['company_id' => $row['company_id']])->id,
            'reference' => 'TRF-CTH-'.++self::$sequence,
        ];
    }
}
