<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `money_fact_balances` (GB) and §11.3: the guard of one money fact of its company — a
// disbursement, other Cash-In, expense, settlement or write-off, an exclusive arc of the kind its fact takes
// (C-32). Σ contras stay within the fact (SF-CONTRA; AX-33; C-45). A refund is fully backed at creation and
// every later unbacked rupiah is a typed shortfall carried by its case, backing released only by the refund's
// own contra (L-46; C-44); a transfer-out backs Cash-In up to its net amount only (BR-FIN-07 b).
return new class extends Migration
{
    private const FACTS = [
        'disbursement_id' => 'disbursements', 'other_cash_receipt_id' => 'other_cash_receipts', 'expense_id' => 'expenses',
        'deduction_settlement_id' => 'deduction_settlements', 'receivable_writeoff_id' => 'receivable_writeoffs',
    ];

    private const AMOUNTS = ['amount', 'contra_amount', 'backed_amount', 'shortfall_amount', 'released_amount', 'transfer_cited_amount'];

    public function up(): void
    {
        Schema::create('money_fact_balances', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('company_id');

            foreach (array_keys(self::FACTS) as $fact) {
                $table->bigInteger($fact)->nullable()->unique();
            }

            $table->text('fact_kind');

            foreach (self::AMOUNTS as $amount) {
                Columns::money($table, $amount)->default(0);
            }

            $table->bigInteger('residual_case_id')->nullable();
            Columns::recordedAt($table);
        });

        $rules = TableRules::for('money_fact_balances');

        foreach (self::FACTS as $fact => $table) {
            $rules->foreign('money_fact_balances_c32_'.substr($fact, 0, -3).'_foreign', ['company_id', $fact], $table, ['company_id', 'id']);
        }

        $rules
            ->foreign('money_fact_balances_c44_residual_case_foreign', ['company_id', 'residual_case_id'], 'correction_cases', ['company_id', 'id'])
            ->codes('money_fact_balances_kind_code', 'fact_kind', ['REFUND', 'TRANSFER_OUT', 'OTHER_DISBURSEMENT', 'CASH_IN', 'EXPENSE', 'SETTLEMENT', 'WRITEOFF'])
            ->check('money_fact_balances_one_fact', 'num_nonnulls('.implode(', ', array_keys(self::FACTS)).') = 1')
            ->check('money_fact_balances_fact_by_kind', <<<'SQL'
                (fact_kind in ('REFUND', 'TRANSFER_OUT', 'OTHER_DISBURSEMENT')) = (disbursement_id is not null)
                and (fact_kind = 'CASH_IN') = (other_cash_receipt_id is not null)
                and (fact_kind = 'EXPENSE') = (expense_id is not null)
                and (fact_kind = 'SETTLEMENT') = (deduction_settlement_id is not null)
                and (fact_kind = 'WRITEOFF') = (receivable_writeoff_id is not null)
                SQL)
            ->check('money_fact_balances_amounts', implode(' and ', array_map(static fn (string $amount): string => "{$amount} >= 0", self::AMOUNTS)))
            ->check('money_fact_balances_c45_contra', 'contra_amount <= amount')
            ->check('money_fact_balances_c44_refund_backed', "fact_kind <> 'REFUND' or backed_amount + shortfall_amount = amount - contra_amount")
            ->check('money_fact_balances_c44_released', 'released_amount <= contra_amount')
            ->check('money_fact_balances_c44_shortfall_case', 'shortfall_amount = 0 or residual_case_id is not null')
            ->check('money_fact_balances_c44_refund_only', "fact_kind = 'REFUND' or (backed_amount = 0 and shortfall_amount = 0 and released_amount = 0)")
            ->check('money_fact_balances_transfer_cited', "transfer_cited_amount <= amount - contra_amount and (fact_kind = 'TRANSFER_OUT' or transfer_cited_amount = 0)")
            // §20 and QS-07: refunds carrying a shortfall.
            ->index('money_fact_balances_qs07_shortfall', 'company_id', 'shortfall_amount > 0')
            ->grant('select, insert')
            ->grantUpdate(['contra_amount', 'backed_amount', 'shortfall_amount', 'released_amount', 'residual_case_id', 'transfer_cited_amount']);
    }

    public function down(): void
    {
        Schema::drop('money_fact_balances');
    }
};
