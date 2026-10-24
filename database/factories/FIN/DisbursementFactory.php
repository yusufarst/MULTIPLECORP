<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\Disbursement;
use App\Modules\ORG\Models\Company;
use Database\Factories\ORG\CompanyBankAccountFactory;
use Database\Factories\PUR\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented Rp2.000.000 payment of a purchase from its company's own account.
 *
 * @extends Factory<Disbursement>
 */
final class DisbursementFactory extends Factory
{
    protected $model = Disbursement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'business_date' => '2026-10-09',
            'amount' => '2000000.00',
            'kind' => 'PURCHASE_PAYMENT',
            'company_bank_account_id' => static fn (array $row): int => CompanyBankAccountFactory::new()->createOne(['company_id' => $row['company_id']])->id,
            'purchase_id' => static fn (array $row): int => PurchaseFactory::new()->createOne(['company_id' => $row['company_id']])->id,
        ];
    }

    /** A refund of the amount to the client, from the company's own account, cited by nothing yet. */
    public static function refund(int $company, int $client, string $amount = '200000.00'): Disbursement
    {
        return self::new()->createOne(['company_id' => $company, 'client_organization_id' => $client, 'kind' => 'REFUND', 'purchase_id' => null, 'amount' => $amount]);
    }
}
