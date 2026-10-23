<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `line_scope_cancellations` (AD): the cancellation of a line's remaining scope (SF-CANCEL-LINE;
// AX-14), released with its reservation in the same transaction; one command cancels several lines, each
// once (§1). A cancellation recorded in error is superseded by a decision of the same line (L-32). The
// quantity holds its line's product scale (§1; C-09).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_scope_cancellations', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('project_line_id');
            $table->bigInteger('company_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::quantity($table, 'qty_base');
            $table->text('reason');
            Columns::user($table, 'decided_by');
            Columns::businessDate($table);
            $table->bigInteger('supersedes_id')->nullable();
            Columns::commandIdentity($table, 'line_scope_cancellations');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('line_scope_cancellations')
            ->unique('line_scope_cancellations_line_key', ['project_line_id', 'id'])
            ->unique('line_scope_cancellations_one_per_line_per_command', ['command_id', 'project_line_id'])
            ->foreign('line_scope_cancellations_c09_line_foreign', ['company_id', 'project_line_id', 'product_id', 'quantity_scale'], 'project_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->foreign('line_scope_cancellations_supersedes_foreign', ['project_line_id', 'supersedes_id'], 'line_scope_cancellations', ['project_line_id', 'id'])
            ->check('line_scope_cancellations_c09_quantity', 'qty_base > 0 and qty_base = round(qty_base, quantity_scale)')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('line_scope_cancellations');
    }
};
