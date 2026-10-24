<?php

namespace App\Modules\FIN\Models;

use App\Modules\DOC\Models\BankStatementLine;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyBankAccount;
use App\Modules\PUR\Models\Purchase;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\OtherCashReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Non-client Cash-In, income or not by its category (DATABASE §4.12 `other_cash_receipts`, AF; L-08;
 * BR-FIN-10), its bank-line citation set once and corrected only with reason (C-19).
 *
 * @property int $id
 * @property int $company_id
 * @property CarbonImmutable $business_date
 * @property string $amount
 * @property string $category
 * @property string $income_class
 * @property int $company_bank_account_id
 * @property int|null $bank_statement_line_id
 * @property CarbonImmutable|null $bank_citation_corrected_at
 * @property int|null $bank_citation_corrected_by
 * @property string|null $bank_citation_corrected_reason
 * @property int|null $transfer_from_disbursement_id
 * @property string|null $transfer_from_disbursement_kind
 * @property int|null $purchase_id
 * @property int|null $correction_case_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(OtherCashReceiptFactory::class)]
class OtherCashReceipt extends Model
{
    /** @use HasFactory<OtherCashReceiptFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<CompanyBankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    /** @return BelongsTo<BankStatementLine, $this> */
    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    /** @return BelongsTo<Disbursement, $this> */
    public function transferFrom(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class, 'transfer_from_disbursement_id');
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<CorrectionCase, $this> */
    public function correctionCase(): BelongsTo
    {
        return $this->belongsTo(CorrectionCase::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'business_date' => 'immutable_date',
            'amount' => 'decimal:2',
            'company_bank_account_id' => 'integer',
            'bank_statement_line_id' => 'integer',
            'bank_citation_corrected_at' => 'immutable_datetime',
            'bank_citation_corrected_by' => 'integer',
            'transfer_from_disbursement_id' => 'integer',
            'purchase_id' => 'integer',
            'correction_case_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
