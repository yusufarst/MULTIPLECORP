<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `financial_contras` (AF) and §13's CONTRA: the contra-fact of one money fact of its company —
// a payment, other Cash-In, disbursement, expense, settlement, opening credit or, as the Owner's supersession,
// a write-off —, an exclusive arc (BR-CR-02; SF-CONTRA; AX-20/33; L-17; C-32). Σ contras per original stays
// within it by the original's guard (C-45). A refund's contra may name the SOURCE_CONTRA-ended application of
// the same refund whose shortfall the returned money resolves (L-46; C-44). An application's contra exit is
// keyed here.
return new class extends Migration
{
    private const ORIGINALS = [
        'payment_id' => 'payments', 'other_cash_receipt_id' => 'other_cash_receipts', 'disbursement_id' => 'disbursements', 'expense_id' => 'expenses',
        'deduction_settlement_id' => 'deduction_settlements', 'opening_customer_credit_id' => 'opening_customer_credits', 'receivable_writeoff_id' => 'receivable_writeoffs',
    ];

    public function up(): void
    {
        Schema::create('financial_contras', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');

            foreach (array_keys(self::ORIGINALS) as $original) {
                $table->bigInteger($original)->nullable()->index();
            }

            Columns::money($table, 'amount');
            $table->bigInteger('resolves_application_id')->nullable();
            $table->text('reason');
            Columns::businessDate($table);
            $table->bigInteger('correction_case_id')->nullable();
            Columns::user($table, 'recorded_by');
            Columns::commandIdentity($table, 'financial_contras');
            Columns::recordedAt($table);
            $table->index('resolves_application_id');
        });

        $rules = TableRules::for('financial_contras')->unique('financial_contras_c32_company_key', ['company_id', 'id']);

        foreach (self::ORIGINALS as $original => $table) {
            $rules->foreign('financial_contras_c32_'.substr($original, 0, -3).'_foreign', ['company_id', $original], $table, ['company_id', 'id']);
        }

        $rules
            ->foreign('financial_contras_c44_resolves_foreign', ['resolves_application_id', 'disbursement_id'], 'payment_applications', ['id', 'refund_disbursement_id'])
            ->foreign('financial_contras_c32_correction_case_foreign', ['company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->check('financial_contras_one_original', 'num_nonnulls('.implode(', ', array_keys(self::ORIGINALS)).') = 1')
            ->check('financial_contras_amount_positive', 'amount > 0')
            ->check('financial_contras_c44_resolves_refund', 'resolves_application_id is null or disbursement_id is not null')
            ->commandIdentity()
            ->grant('select, insert');

        TableRules::for('payment_applications')
            ->foreign('payment_applications_c44_end_contra_foreign', ['company_id', 'end_contra_id'], 'financial_contras', ['company_id', 'id']);
    }

    public function down(): void
    {
        DB::statement('alter table payment_applications drop constraint payment_applications_c44_end_contra_foreign');
        Schema::drop('financial_contras');
    }
};
