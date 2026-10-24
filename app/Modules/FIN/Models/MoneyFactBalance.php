<?php

namespace App\Modules\FIN\Models;

use App\Modules\OPS\Models\CorrectionCase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The guard of one money fact's contras, a refund's backing and a transfer's citation (DATABASE §4.12
 * `money_fact_balances`, GB; C-44, C-45). It has no factory: a balance belongs to its posting service
 * (ARCHITECTURE §7, §19).
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $disbursement_id
 * @property int|null $other_cash_receipt_id
 * @property int|null $expense_id
 * @property int|null $deduction_settlement_id
 * @property int|null $receivable_writeoff_id
 * @property string $fact_kind
 * @property string $amount
 * @property string $contra_amount
 * @property string $backed_amount
 * @property string $shortfall_amount
 * @property string $released_amount
 * @property string $transfer_cited_amount
 * @property int|null $residual_case_id
 * @property CarbonImmutable $recorded_at
 */
class MoneyFactBalance extends Model
{
    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Disbursement, $this> */
    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class);
    }

    /** @return BelongsTo<CorrectionCase, $this> */
    public function residualCase(): BelongsTo
    {
        return $this->belongsTo(CorrectionCase::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'disbursement_id' => 'integer',
            'other_cash_receipt_id' => 'integer',
            'expense_id' => 'integer',
            'deduction_settlement_id' => 'integer',
            'receivable_writeoff_id' => 'integer',
            'amount' => 'decimal:2',
            'contra_amount' => 'decimal:2',
            'backed_amount' => 'decimal:2',
            'shortfall_amount' => 'decimal:2',
            'released_amount' => 'decimal:2',
            'transfer_cited_amount' => 'decimal:2',
            'residual_case_id' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
