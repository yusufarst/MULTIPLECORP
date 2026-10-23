<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\BankStatementLine;
use App\Modules\ORG\Models\Company;
use Database\Factories\ORG\CompanyBankAccountFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented credit line on the company's own account and its own bank statement.
 *
 * @extends Factory<BankStatementLine>
 */
final class BankStatementLineFactory extends Factory
{
    protected $model = BankStatementLine::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'statement_evidence_type' => 'BANK_STATEMENT',
            'business_date' => '2026-10-01',
            'amount' => '1500000.00',
            'line_reference' => 'TRX-CTH-'.++self::$sequence,
        ];
    }

    /** The account and the statement belong to the line's company (C-32). */
    public function configure(): static
    {
        return $this->afterMaking(function (BankStatementLine $line): void {
            if ($line->getAttribute('company_bank_account_id') === null) {
                $line->company_bank_account_id = CompanyBankAccountFactory::new()->createOne(['company_id' => $line->company_id])->id;
            }

            if ($line->getAttribute('statement_evidence_id') === null) {
                $line->statement_evidence_id = EvidenceDocumentFactory::new()->bankStatement()->createOne(['company_id' => $line->company_id])->id;
            }
        });
    }
}
