<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\OtherCashReceipt;
use App\Modules\ORG\Models\Company;
use Database\Factories\ORG\CompanyBankAccountFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented Rp300.000 supplier refund into its company's own account, not income.
 *
 * @extends Factory<OtherCashReceipt>
 */
final class OtherCashReceiptFactory extends Factory
{
    protected $model = OtherCashReceipt::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'business_date' => '2026-10-09',
            'amount' => '300000.00',
            'category' => 'SUPPLIER_REFUND',
            'income_class' => 'NON_INCOME',
            'company_bank_account_id' => static fn (array $row): int => CompanyBankAccountFactory::new()->createOne(['company_id' => $row['company_id']])->id,
        ];
    }
}
