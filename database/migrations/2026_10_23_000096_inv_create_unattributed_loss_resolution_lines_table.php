<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `unattributed_loss_resolution_lines` (AD) and §5.8: the economic assignment of a resolution
// within its case, at the case's product scale (§1; C-09), only to a candidate company — through its
// exposure row (C-37) —, its causal project that company's (C-32). Its lot closures are the consumptions or
// condition entries that cite it, through (`id`, `assigned_company_id`).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unattributed_loss_resolution_lines', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('unattributed_loss_resolution_id');
            $table->bigInteger('unattributed_loss_case_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->bigInteger('assigned_company_id');
            Columns::quantity($table, 'assigned_qty_base');
            $table->bigInteger('bearer_project_id')->nullable();
            Columns::commandIdentity($table, 'unattributed_loss_resolution_lines');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('unattributed_loss_resolution_id');
        });

        TableRules::for('unattributed_loss_resolution_lines')
            ->unique('unattributed_loss_resolution_lines_c28_bearer_key', ['id', 'assigned_company_id'])
            ->foreign('unattributed_loss_resolution_lines_resolution_foreign', ['unattributed_loss_resolution_id', 'unattributed_loss_case_id'], 'unattributed_loss_resolutions', ['id', 'unattributed_loss_case_id'])
            ->foreign('unattributed_loss_resolution_lines_c09_case_foreign', ['unattributed_loss_case_id', 'product_id', 'quantity_scale'], 'unattributed_loss_cases', ['id', 'product_id', 'quantity_scale'])
            ->foreign('unattributed_loss_resolution_lines_c37_exposure_foreign', ['unattributed_loss_case_id', 'assigned_company_id'], 'unattributed_loss_exposures', ['unattributed_loss_case_id', 'company_id'])
            ->foreign('unattributed_loss_resolution_lines_c32_bearer_project_foreign', ['assigned_company_id', 'bearer_project_id'], 'projects', ['company_id', 'id'])
            ->check('unattributed_loss_resolution_lines_c09_quantity', 'assigned_qty_base > 0 and assigned_qty_base = round(assigned_qty_base, quantity_scale)')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('unattributed_loss_resolution_lines');
    }
};
