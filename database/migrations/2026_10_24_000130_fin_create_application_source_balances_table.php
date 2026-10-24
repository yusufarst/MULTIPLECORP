<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `application_source_balances` (GB) and §11.3: the guard of one application source — a payment
// or an opening credit, an exclusive arc of its company and client (C-14). Invoice applications and refund-
// backed consumption never exceed the source's real money (BR-FIN-03; C-13); a source with an open refund
// shortfall keeps no invoice application and no free capacity (L-46; C-44); payment + its settlements never
// exceed the claimed gross, and a fully contra'd payment keeps no active settlement (L-28; C-22, C-45). The
// capacity is written over base columns, as one generated column cannot read another.
return new class extends Migration
{
    private const AMOUNTS = [
        'gross_amount', 'contra_amount', 'invoice_applied_amount', 'refund_backed_amount', 'refund_released_amount', 'refund_shortfall_amount',
        'settled_amount', 'claimed_deductions_amount',
    ];

    public function up(): void
    {
        Schema::create('application_source_balances', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('payment_id')->nullable()->unique();
            $table->bigInteger('opening_customer_credit_id')->nullable()->unique();
            $table->bigInteger('company_id');
            $table->bigInteger('client_organization_id');

            foreach (self::AMOUNTS as $amount) {
                Columns::money($table, $amount)->default(0);
            }

            Columns::money($table, 'net_amount')->storedAs('gross_amount - contra_amount');
            Columns::money($table, 'capacity_amount')->storedAs('gross_amount - contra_amount - invoice_applied_amount - refund_backed_amount');
            Columns::recordedAt($table);
        });

        TableRules::for('application_source_balances')
            ->foreign('application_source_balances_c14_payment_foreign', ['company_id', 'client_organization_id', 'payment_id'], 'payments', ['company_id', 'client_organization_id', 'id'])
            ->foreign('application_source_balances_c14_credit_foreign', ['company_id', 'client_organization_id', 'opening_customer_credit_id'], 'opening_customer_credits', ['company_id', 'client_organization_id', 'id'])
            ->check('application_source_balances_one_source', 'num_nonnulls(payment_id, opening_customer_credit_id) = 1')
            ->check('application_source_balances_amounts', implode(' and ', array_map(static fn (string $amount): string => "{$amount} >= 0", self::AMOUNTS)).' and net_amount >= 0')
            ->check('application_source_balances_c13_capacity', 'capacity_amount >= 0')
            ->check('application_source_balances_c44_shortfall', 'refund_shortfall_amount = 0 or (invoice_applied_amount = 0 and capacity_amount = 0)')
            ->check('application_source_balances_c22_settled', 'settled_amount <= claimed_deductions_amount')
            ->check('application_source_balances_c45_contra_settled', 'net_amount > 0 or settled_amount = 0')
            // IX-14: sources with unapplied capacity or unsettled claimed deductions (QS-13, QS-15).
            ->index('application_source_balances_ix14_open', 'company_id', 'capacity_amount > 0 or settled_amount < claimed_deductions_amount')
            ->grant('select, insert')
            ->grantUpdate(array_values(array_diff(self::AMOUNTS, ['gross_amount'])));
    }

    public function down(): void
    {
        Schema::drop('application_source_balances');
    }
};
