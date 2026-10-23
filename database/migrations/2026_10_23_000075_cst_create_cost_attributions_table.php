<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.8 `cost_attributions` (AF) and §6: the one managerial cost ledger — HPP, loss and supplier-
// return cost, each signed amount sourced from exactly one consumption, drop-ship confirmation line or
// non-stock purchase line (D-DB-10). A lot draw carries its lot and source company and no component, for it
// draws the lot's blended cost; a direct attribution carries one component per row, a CHARGE row with the
// same charge, share, re-spread and version references as a lot entry (C-57). One INITIAL attribution per
// consumption, and per component and charge of a drop-ship or non-stock line, a NULL charge counted as one
// value (BR-FIN-12; L-18; C-25). The bearer is a company and optionally its project (C-32); a bearer other
// than the lot's source needs its reason (L-44; C-28), and a case is the source's or the bearer's (§15).
// The consumption, the drop-ship line, the restoration and the consumption's composite key are TASK-008's
// (DIR-088's rule), with the trigger holding the bearer to the consumption's.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_attributions', function (Blueprint $table) {
            Columns::id($table);
            $table->text('cost_class');
            $table->text('cause');
            Columns::money($table, 'amount');
            $table->bigInteger('stock_consumption_id')->nullable();
            $table->bigInteger('dropship_confirmation_line_id')->nullable();
            Columns::reference($table, 'purchase_line_id', 'purchase_lines')->nullable();
            $table->text('cost_component')->nullable();
            Columns::reference($table, 'purchase_charge_id', 'purchase_charges')->nullable();
            Columns::reference($table, 'purchase_charge_line_share_id', 'purchase_charge_line_shares')->nullable();
            Columns::reference($table, 'respread_from_purchase_line_id', 'purchase_lines')->nullable();
            $table->integer('allocation_version')->nullable();
            $table->bigInteger('lot_id')->nullable();
            Columns::reference($table, 'source_company_id', 'companies')->nullable();
            $table->bigInteger('stock_restoration_id')->nullable();
            $table->bigInteger('correction_case_id')->nullable();
            $table->bigInteger('case_company_id')->nullable();
            Columns::reference($table, 'bearer_company_id', 'companies');
            $table->bigInteger('bearer_project_id')->nullable();
            $table->text('allocation_reason')->nullable();
            Columns::reference($table, 'reverses_attribution_id', 'cost_attributions')->nullable();
            Columns::businessDate($table);
            Columns::commandIdentity($table, 'cost_attributions');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('cost_attributions')
            ->foreign('cost_attributions_c32_bearer_project_foreign', ['bearer_company_id', 'bearer_project_id'], 'projects', ['company_id', 'id'])
            ->foreign('cost_attributions_c32_correction_case_foreign', ['case_company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->codes('cost_attributions_cost_class_code', 'cost_class', ['HPP', 'LOSS', 'SUPPLIER_RETURN'])
            ->codes('cost_attributions_cause_code', 'cause', [
                'INITIAL', 'RESTORATION_CONTRA', 'COST_CORRECTION', 'CHARGE_RESPREAD', 'LATE_CHARGE', 'RECLASSIFICATION', 'LOSS_REVERSAL',
                'SUBSTITUTION', 'RETURN_SETTLEMENT',
            ])
            ->codes('cost_attributions_cost_component_code', 'cost_component', ['GOODS', 'NONCREDITABLE_TAX', 'CHARGE', 'BASIS'], nullable: true)
            ->check('cost_attributions_amount_not_zero', 'amount <> 0')
            ->check('cost_attributions_one_source', 'num_nonnulls(stock_consumption_id, dropship_confirmation_line_id, purchase_line_id) = 1')
            // A lot draw names its lot and source and no component; a direct attribution states its component.
            ->check('cost_attributions_lot_draw', <<<'SQL'
                (stock_consumption_id is not null) = (lot_id is not null)
                and (stock_consumption_id is null or source_company_id is not null)
                and (stock_consumption_id is null) = (cost_component is not null)
                SQL)
            ->check('cost_attributions_c57_charge_references', <<<'SQL'
                coalesce(cost_component = 'CHARGE', false) = (purchase_charge_id is not null)
                and coalesce(cost_component = 'CHARGE', false) = (allocation_version is not null)
                and coalesce(cost_component = 'CHARGE', false) = (num_nonnulls(purchase_charge_line_share_id, respread_from_purchase_line_id) = 1)
                and (coalesce(cost_component = 'CHARGE', false) or num_nonnulls(purchase_charge_line_share_id, respread_from_purchase_line_id) = 0)
                SQL)
            ->check('cost_attributions_case_pair', 'num_nonnulls(correction_case_id, case_company_id) in (0, 2)')
            ->check('cost_attributions_case_side', 'case_company_id is null or case_company_id = bearer_company_id or (source_company_id is not null and case_company_id = source_company_id)')
            ->check('cost_attributions_c28_allocation_reason', 'source_company_id is null or source_company_id = bearer_company_id or allocation_reason is not null')
            ->uniqueIndex('cost_attributions_c25_one_initial_per_consumption', 'stock_consumption_id', "cause = 'INITIAL' and stock_consumption_id is not null")
            ->uniqueIndex('cost_attributions_c25_one_initial_per_dropship_line', 'dropship_confirmation_line_id, cost_component, coalesce(purchase_charge_id, 0)', "cause = 'INITIAL' and dropship_confirmation_line_id is not null")
            ->uniqueIndex('cost_attributions_c25_one_initial_per_purchase_line', 'purchase_line_id, cost_component, coalesce(purchase_charge_id, 0)', "cause = 'INITIAL' and purchase_line_id is not null")
            // IX-08: the cascade derivations' reads by consumption and by lot.
            ->index('cost_attributions_ix08_consumption', 'stock_consumption_id')
            ->index('cost_attributions_ix08_lot', 'lot_id')
            // IX-16: the first-use proof that no posting cites a share yet (FU-).
            ->index('cost_attributions_share', 'purchase_charge_line_share_id', 'purchase_charge_line_share_id is not null')
            // §20: company and date reporting, a project's cost (CALC-08–CALC-11).
            ->index('cost_attributions_bearer_business_date', 'bearer_company_id, business_date')
            ->index('cost_attributions_bearer_project', 'bearer_project_id')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('cost_attributions');
    }
};
