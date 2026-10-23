<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `project_commercial_balances` (GB): the project invoice cap (L-16; AX-16), posted by
// DemandPosting (§3.1) — what the current pins confirm against what the current LIVE invoice versions
// invoice, never more invoiced than confirmed (C-31).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_commercial_balances', function (Blueprint $table) {
            $table->bigInteger('project_id')->primary();
            Columns::money($table, 'confirmed_sales_value')->default(0);
            Columns::money($table, 'invoiced_sales_value')->default(0);
            Columns::recordedAt($table);
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
        });

        TableRules::for('project_commercial_balances')
            ->check('project_commercial_balances_not_negative', 'confirmed_sales_value >= 0 and invoiced_sales_value >= 0')
            ->check('project_commercial_balances_c31_invoice_cap', 'invoiced_sales_value <= confirmed_sales_value')
            // IX-14: the projects with confirmed value not yet invoiced, for a scope of all companies (QS-15).
            ->index('project_commercial_balances_ix14_uninvoiced', 'project_id', 'confirmed_sales_value > invoiced_sales_value')
            ->grant('select, insert')
            ->grantUpdate(['confirmed_sales_value', 'invoiced_sales_value']);
    }

    public function down(): void
    {
        Schema::drop('project_commercial_balances');
    }
};
