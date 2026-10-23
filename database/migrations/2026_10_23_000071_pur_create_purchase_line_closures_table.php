<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.6 `purchase_line_closures` (AD): the closure of a purchase line's remainder (BR-PUR-04), with
// AX-32's re-spread of the pending charge shares; one command closes several lines, each once (§1). A
// closure recorded in error is superseded once — a linear chain — with the exact inverse posting (CM-27;
// C-59). The quantity holds its line's product scale (§1; C-09).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_line_closures', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('purchase_line_id');
            $table->bigInteger('company_id');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::quantity($table, 'closed_qty_base');
            $table->text('reason');
            Columns::user($table, 'decided_by');
            Columns::businessDate($table);
            $table->bigInteger('supersedes_id')->nullable();
            Columns::commandIdentity($table, 'purchase_line_closures');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('purchase_line_closures')
            ->unique('purchase_line_closures_line_key', ['purchase_line_id', 'id'])
            ->unique('purchase_line_closures_c59_linear_chain', ['supersedes_id'])
            ->unique('purchase_line_closures_one_per_line_per_command', ['command_id', 'purchase_line_id'])
            ->foreign('purchase_line_closures_c09_line_foreign', ['company_id', 'purchase_line_id', 'product_id', 'quantity_scale'], 'purchase_lines', ['company_id', 'id', 'product_id', 'quantity_scale'])
            ->foreign('purchase_line_closures_c59_supersedes_foreign', ['purchase_line_id', 'supersedes_id'], 'purchase_line_closures', ['purchase_line_id', 'id'])
            ->check('purchase_line_closures_c09_quantity', 'closed_qty_base > 0 and closed_qty_base = round(closed_qty_base, quantity_scale)')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('purchase_line_closures');
    }
};
