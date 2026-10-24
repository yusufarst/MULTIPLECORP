<?php

namespace App\Modules\FIN\Models;

use App\Modules\CST\Models\Expense;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\FinancialContraFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The contra-fact of one money fact, or the Owner's supersession of a write-off (DATABASE §4.12
 * `financial_contras`, AF; BR-CR-02; AX-20/33).
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $payment_id
 * @property int|null $other_cash_receipt_id
 * @property int|null $disbursement_id
 * @property int|null $expense_id
 * @property int|null $deduction_settlement_id
 * @property int|null $opening_customer_credit_id
 * @property int|null $receivable_writeoff_id
 * @property string $amount
 * @property int|null $resolves_application_id
 * @property string $reason
 * @property CarbonImmutable $business_date
 * @property int|null $correction_case_id
 * @property int $recorded_by
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(FinancialContraFactory::class)]
class FinancialContra extends Model
{
    use AppendOnly;

    /** @use HasFactory<FinancialContraFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<OtherCashReceipt, $this> */
    public function otherCashReceipt(): BelongsTo
    {
        return $this->belongsTo(OtherCashReceipt::class);
    }

    /** @return BelongsTo<Disbursement, $this> */
    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class);
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** @return BelongsTo<DeductionSettlement, $this> */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(DeductionSettlement::class, 'deduction_settlement_id');
    }

    /** @return BelongsTo<OpeningCustomerCredit, $this> */
    public function openingCredit(): BelongsTo
    {
        return $this->belongsTo(OpeningCustomerCredit::class, 'opening_customer_credit_id');
    }

    /** @return BelongsTo<ReceivableWriteoff, $this> */
    public function writeoff(): BelongsTo
    {
        return $this->belongsTo(ReceivableWriteoff::class, 'receivable_writeoff_id');
    }

    /** @return BelongsTo<PaymentApplication, $this> */
    public function resolves(): BelongsTo
    {
        return $this->belongsTo(PaymentApplication::class, 'resolves_application_id');
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
            'payment_id' => 'integer',
            'other_cash_receipt_id' => 'integer',
            'disbursement_id' => 'integer',
            'expense_id' => 'integer',
            'deduction_settlement_id' => 'integer',
            'opening_customer_credit_id' => 'integer',
            'receivable_writeoff_id' => 'integer',
            'amount' => 'decimal:2',
            'resolves_application_id' => 'integer',
            'business_date' => 'immutable_date',
            'correction_case_id' => 'integer',
            'recorded_by' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
