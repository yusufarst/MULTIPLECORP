<?php

namespace Database\Factories\ORG;

use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyBankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented account of an invented bank.
 *
 * @extends Factory<CompanyBankAccount>
 */
final class CompanyBankAccountFactory extends Factory
{
    protected $model = CompanyBankAccount::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'company_id' => Company::factory(),
            'bank_name' => 'Bank Contoh',
            'account_number' => '9900'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'account_holder' => 'CV Contoh Usaha',
        ];
    }
}
