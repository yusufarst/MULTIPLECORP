<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.9 `delivery_closures` (AD): the formal closure of a shipped quantity the client never received —
// lost in transit, returned undelivered or refused — of a dispatch line or a drop-ship confirmation line, an
// exclusive arc (§1), at its demand line's product scale (§1; C-09), each reference its company's (C-32)
// (WF-FUL-02; CM-06/07). A closure decided in error is superseded once, a linear chain (L-32; C-59).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_closures', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('project_line_id');
            $table->bigInteger('company_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->bigInteger('dispatch_line_id')->nullable();
            $table->bigInteger('dropship_confirmation_line_id')->nullable();
            Columns::quantity($table, 'closed_qty_base');
            $table->text('closure_kind');
            $table->text('reason');
            $table->bigInteger('correction_case_id')->nullable();
            Columns::user($table, 'decided_by');
            Columns::businessDate($table);
            $table->bigInteger('supersedes_id')->nullable();
            Columns::commandIdentity($table, 'delivery_closures');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('dispatch_line_id');
            $table->index('dropship_confirmation_line_id');
        });

        TableRules::for('delivery_closures')
            ->unique('delivery_closures_line_key', ['project_line_id', 'id'])
            ->unique('delivery_closures_c59_linear_chain', ['supersedes_id'])
            ->foreign('delivery_closures_c09_project_line_foreign', ['company_id', 'project_line_id', 'product_id', 'quantity_scale'], 'project_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->foreign('delivery_closures_c32_dispatch_line_foreign', ['company_id', 'dispatch_line_id'], 'dispatch_lines', ['company_id', 'id'])
            ->foreign('delivery_closures_c32_dropship_line_foreign', ['company_id', 'dropship_confirmation_line_id'], 'dropship_confirmation_lines', ['company_id', 'id'])
            ->foreign('delivery_closures_c32_correction_case_foreign', ['company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->foreign('delivery_closures_c59_supersedes_foreign', ['project_line_id', 'supersedes_id'], 'delivery_closures', ['project_line_id', 'id'])
            ->codes('delivery_closures_kind_code', 'closure_kind', ['LOST_IN_TRANSIT', 'RETURNED_UNDELIVERED', 'REFUSED'])
            ->check('delivery_closures_one_shipment', 'num_nonnulls(dispatch_line_id, dropship_confirmation_line_id) = 1')
            ->check('delivery_closures_c09_quantity', 'closed_qty_base > 0 and closed_qty_base = round(closed_qty_base, quantity_scale)')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('delivery_closures');
    }
};
