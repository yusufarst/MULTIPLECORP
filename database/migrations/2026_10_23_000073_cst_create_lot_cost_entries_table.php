<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.8 `lot_cost_entries` (AF) and §6: every signed amount of a lot's acquisition cost, each with
// the cost component it carries — fixed by its kind for the goods, tax, charge-share, re-spread and basis
// kinds, stated on every correction and receipt-reversal row —, so every posted-value guard can be rebuilt
// (§3.1; C-57). A CHARGE row names its charge and allocation version and either its share (the share
// schedule's rows) or the line whose ended remainder it re-spreads, never both. Exactly one basis per lot,
// one non-creditable tax row and one row per charge share, so no lot cost is posted twice (BR-INV-04;
// BR-PUR-05; BR-FIN-17). The lot, the stock restoration and the basis kind's agreement with the lot's
// `acquisition_kind` are TASK-008's (DIR-088's rule); the purchase records and the case are the lot's
// source company's (C-32).
return new class extends Migration
{
    private const BASIS_KINDS = "'GOODS', 'OPENING_COST', 'SURPLUS_FLAGGED_BASIS', 'DROPSHIP_RETURN_BASIS', 'REPLACEMENT_BASIS'";

    public function up(): void
    {
        Schema::create('lot_cost_entries', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('lot_id');
            Columns::reference($table, 'source_company_id', 'companies');
            $table->text('entry_kind');
            $table->text('cost_component');
            Columns::money($table, 'amount');
            $table->bigInteger('purchase_line_id')->nullable();
            $table->bigInteger('purchase_charge_id')->nullable();
            $table->bigInteger('purchase_charge_line_share_id')->nullable();
            $table->bigInteger('respread_from_purchase_line_id')->nullable();
            $table->integer('allocation_version')->nullable();
            $table->bigInteger('purchase_line_adjustment_id')->nullable();
            Columns::reference($table, 'import_row_id', 'import_rows')->nullable();
            $table->bigInteger('stock_restoration_id')->nullable();
            $table->bigInteger('correction_case_id')->nullable();
            Columns::businessDate($table);
            Columns::commandIdentity($table, 'lot_cost_entries');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('lot_id');
        });

        TableRules::for('lot_cost_entries')
            ->foreign('lot_cost_entries_c32_purchase_line_foreign', ['source_company_id', 'purchase_line_id'], 'purchase_lines', ['company_id', 'id'])
            ->foreign('lot_cost_entries_c32_charge_foreign', ['source_company_id', 'purchase_charge_id'], 'purchase_charges', ['company_id', 'id'])
            ->foreign('lot_cost_entries_c32_share_foreign', ['source_company_id', 'purchase_charge_line_share_id'], 'purchase_charge_line_shares', ['company_id', 'id'])
            ->foreign('lot_cost_entries_c32_respread_line_foreign', ['source_company_id', 'respread_from_purchase_line_id'], 'purchase_lines', ['company_id', 'id'])
            ->foreign('lot_cost_entries_c32_adjustment_foreign', ['source_company_id', 'purchase_line_adjustment_id'], 'purchase_line_adjustments', ['company_id', 'id'])
            ->foreign('lot_cost_entries_c32_correction_case_foreign', ['source_company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->codes('lot_cost_entries_entry_kind_code', 'entry_kind', [
                'GOODS', 'NONCREDITABLE_TAX', 'CHARGE_SHARE', 'CHARGE_RESPREAD', 'COST_CORRECTION', 'OPENING_COST', 'SURPLUS_FLAGGED_BASIS',
                'DROPSHIP_RETURN_BASIS', 'REPLACEMENT_BASIS', 'RECEIPT_REVERSAL', 'BASIS_REVERSAL',
            ])
            ->codes('lot_cost_entries_cost_component_code', 'cost_component', ['GOODS', 'NONCREDITABLE_TAX', 'CHARGE', 'BASIS'])
            ->check('lot_cost_entries_c57_component_by_kind', <<<'SQL'
                case
                    when entry_kind = 'GOODS' then cost_component = 'GOODS'
                    when entry_kind = 'NONCREDITABLE_TAX' then cost_component = 'NONCREDITABLE_TAX'
                    when entry_kind in ('CHARGE_SHARE', 'CHARGE_RESPREAD') then cost_component = 'CHARGE'
                    when entry_kind in ('OPENING_COST', 'SURPLUS_FLAGGED_BASIS', 'DROPSHIP_RETURN_BASIS', 'REPLACEMENT_BASIS', 'BASIS_REVERSAL') then cost_component = 'BASIS'
                    else true
                end
                SQL)
            ->check('lot_cost_entries_c57_charge_references', <<<'SQL'
                (cost_component = 'CHARGE') = (purchase_charge_id is not null)
                and (cost_component = 'CHARGE') = (allocation_version is not null)
                and (cost_component = 'CHARGE') = (num_nonnulls(purchase_charge_line_share_id, respread_from_purchase_line_id) = 1)
                and (cost_component = 'CHARGE' or num_nonnulls(purchase_charge_line_share_id, respread_from_purchase_line_id) = 0)
                and (entry_kind <> 'CHARGE_SHARE' or purchase_charge_line_share_id is not null)
                and (entry_kind <> 'CHARGE_RESPREAD' or respread_from_purchase_line_id is not null)
                SQL)
            ->uniqueIndex('lot_cost_entries_one_basis', 'lot_id', 'entry_kind in ('.self::BASIS_KINDS.')')
            ->uniqueIndex('lot_cost_entries_one_noncreditable_tax', 'lot_id', "entry_kind = 'NONCREDITABLE_TAX'")
            ->uniqueIndex('lot_cost_entries_one_charge_share', 'lot_id, purchase_charge_line_share_id', "entry_kind = 'CHARGE_SHARE'")
            // IX-08 and §20: a purchase line's cost by component, a charge's postings by version.
            ->index('lot_cost_entries_ix08_purchase_line', 'purchase_line_id, cost_component')
            ->index('lot_cost_entries_charge_version', 'purchase_charge_id, allocation_version')
            ->index('lot_cost_entries_share', 'purchase_charge_line_share_id', 'purchase_charge_line_share_id is not null')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('lot_cost_entries');
    }
};
