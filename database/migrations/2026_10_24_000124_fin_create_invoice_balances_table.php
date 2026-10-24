<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `invoice_balances` (GB) and §11.2: the receivable guard of an invoice (BR-FIN-04; C-15) — its
// current effective version's values, 0 when voided, beside what applications, fee and tax settlements and
// net write-offs took from it; their sum never exceeds the gross, so the generated outstanding is never
// negative and a void or downward revision cannot commit without disposing them (C-16). Collected tax never
// exceeds the output tax (C-22). The current billing act, its invoice's own, and its due date serve aging.
return new class extends Migration
{
    private const AMOUNTS = ['gross_value', 'sales_value', 'output_tax_value', 'applied_amount', 'fee_settled_amount', 'tax_settled_amount', 'collected_tax_settled_amount', 'written_off_amount'];

    public function up(): void
    {
        Schema::create('invoice_balances', function (Blueprint $table) {
            $table->bigInteger('invoice_id')->primary();
            $table->bigInteger('company_id');

            foreach (self::AMOUNTS as $amount) {
                Columns::money($table, $amount)->default(0);
            }

            $table->bigInteger('current_billing_act_id')->nullable();
            $table->date('current_due_date')->nullable();
            Columns::money($table, 'outstanding')->storedAs('gross_value - applied_amount - fee_settled_amount - tax_settled_amount - written_off_amount');
            Columns::recordedAt($table);
        });

        TableRules::for('invoice_balances')
            ->foreign('invoice_balances_c32_invoice_foreign', ['company_id', 'invoice_id'], 'invoices', ['company_id', 'id'])
            ->foreign('invoice_balances_c41_current_act_foreign', ['invoice_id', 'current_billing_act_id'], 'invoice_billing_acts', ['invoice_id', 'id'])
            ->check('invoice_balances_c15_amounts', implode(' and ', array_map(static fn (string $amount): string => "{$amount} >= 0", self::AMOUNTS)))
            ->check('invoice_balances_c15_conservation', 'applied_amount + fee_settled_amount + tax_settled_amount + written_off_amount <= gross_value')
            ->check('invoice_balances_c22_collected_tax', 'collected_tax_settled_amount <= output_tax_value')
            ->check('invoice_balances_due_with_act', 'current_due_date is null or current_billing_act_id is not null')
            // §20 and IX-11: active receivables by due date, the keyset order of aging.
            ->index('invoice_balances_ix11_due', 'company_id, current_due_date, invoice_id', 'outstanding > 0')
            ->grant('select, insert')
            ->grantUpdate([...self::AMOUNTS, 'current_billing_act_id', 'current_due_date']);
    }

    public function down(): void
    {
        Schema::drop('invoice_balances');
    }
};
