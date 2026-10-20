<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.13 `correction_cases` (SR): one case gathers every action of a correction (§13; BR-CR-04).
// Its six counters are guard columns of §3.1, posted only by MoneyPosting and CostPosting in the
// transactions of the case's facts, never by users, and rebuildable; its closure CHECKs refuse a closed
// case with a residual or an unbalanced purchase-return equation, so a correction reopens it in the
// same transaction (C-46). `company_id`, `project_id` and the informational cross-company links
// `causal_project_id` and `source_lot_id` (§15) reference the tables of TASK-006–TASK-008.
return new class extends Migration
{
    private const COUNTERS = [
        'unbacked_refund_amount', 'residual_settlement_amount', 'returned_cost',
        'refunded_value', 'replaced_value', 'unrecovered_value',
    ];

    public function up(): void
    {
        Schema::create('correction_cases', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->bigInteger('company_id');
            $table->bigInteger('project_id')->nullable();
            $table->text('case_kind');
            $table->text('state')->default('OPEN');
            $table->text('reason')->nullable();
            $table->bigInteger('causal_project_id')->nullable();
            $table->bigInteger('source_lot_id')->nullable();

            foreach (self::COUNTERS as $counter) {
                Columns::money($table, $counter)->default(0);
            }

            $table->text('closure_outcome')->nullable();
            Columns::user($table, 'closed_by')->nullable();
            Columns::timestamp($table, 'closed_at')->nullable();
            Columns::user($table, 'reopened_by')->nullable();
            Columns::timestamp($table, 'reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->uuid('reopen_command_id')->nullable();
            Columns::commandIdentity($table, 'correction_cases');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
            // The restricted delete of a draft project checks these (§20).
            $table->index(['company_id', 'project_id']);
            $table->index('causal_project_id');
        });

        TableRules::for('correction_cases')
            // The key every `correction_case_id` reference carries its company in (§1, §15; C-32).
            ->unique('correction_cases_c32_company_key', ['company_id', 'id'])
            ->codes('correction_cases_kind_code', 'case_kind', [
                'SALES_RETURN', 'DELIVERY_DISCREPANCY', 'DAMAGED_UNIT', 'PURCHASE_RETURN', 'DROPSHIP_RETURN',
                'PAYMENT_CORRECTION', 'INVOICE_CORRECTION', 'OPENING_CORRECTION', 'OTHER',
            ])
            ->codes('correction_cases_state_code', 'state', ['OPEN', 'CLOSED'])
            ->check('correction_cases_c46_no_residual_when_closed', "state = 'OPEN' or (unbacked_refund_amount = 0 and residual_settlement_amount = 0)")
            ->check('correction_cases_c46_purchase_return_equation', "case_kind <> 'PURCHASE_RETURN' or state = 'OPEN' or returned_cost = refunded_value + replaced_value + unrecovered_value")
            // QS-07: the company's open cases (§20).
            ->index('correction_cases_open', 'company_id, id', "state = 'OPEN'")
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate([
                'state', 'closure_outcome', 'closed_by', 'closed_at',
                'reopened_by', 'reopened_at', 'reopen_reason', 'reopen_command_id',
                ...self::COUNTERS,
                'lock_version', 'updated_at',
            ]);
    }

    public function down(): void
    {
        Schema::drop('correction_cases');
    }
};
