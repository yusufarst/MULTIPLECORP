<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.6 `purchase_charge_line_shares` (AF, versioned): a charge's deterministic, residual-absorbing
// share on each line (BR-FIN-13), by its basis value. A basis correction (CM-35) or an amount correction
// (CM-17) adds a version, never edits one; `purchase_charge_share_balances` names the current version.
// The charge and the line are the same company's (C-32).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_charge_line_shares', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('purchase_charge_id');
            $table->bigInteger('purchase_line_id');
            $table->bigInteger('company_id');
            // A line value, a quantity or a weight, each exact at three decimals at most.
            $table->decimal('basis_value', 18, 3);
            Columns::money($table, 'share_amount');
            $table->integer('allocation_version');
            Columns::commandIdentity($table, 'purchase_charge_line_shares');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('purchase_line_id');
        });

        TableRules::for('purchase_charge_line_shares')
            ->unique('purchase_charge_line_shares_version_key', ['purchase_charge_id', 'purchase_line_id', 'allocation_version'])
            ->unique('purchase_charge_line_shares_c32_company_key', ['company_id', 'id'])
            ->foreign('purchase_charge_line_shares_c32_charge_foreign', ['company_id', 'purchase_charge_id'], 'purchase_charges', ['company_id', 'id'])
            ->foreign('purchase_charge_line_shares_c32_line_foreign', ['company_id', 'purchase_line_id'], 'purchase_lines', ['company_id', 'id'])
            ->check('purchase_charge_line_shares_values', 'basis_value >= 0 and share_amount >= 0 and allocation_version >= 1')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('purchase_charge_line_shares');
    }
};
