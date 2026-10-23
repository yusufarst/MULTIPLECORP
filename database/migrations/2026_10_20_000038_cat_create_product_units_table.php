<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.4 `product_units` (MD) and §7: the base unit at ratio 1 and alternates as exact integer
// multiples of it (L-31), so conversion multiplies and never rounds. A ratio edit never reaches history,
// because each transaction snapshots the ratio it used (§7; annex 11).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'product_id', 'products');
            Columns::reference($table, 'unit_id', 'units');
            $table->bigInteger('ratio_to_base');
            $table->boolean('is_base')->default(false);
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'product_units');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('product_units')
            ->unique('product_units_unit_unique', ['product_id', 'unit_id'])
            ->uniqueIndex('product_units_one_base', 'product_id', 'is_base')
            ->check('product_units_ratio_positive', 'ratio_to_base >= 1')
            ->check('product_units_base_ratio_one', 'not is_base or ratio_to_base = 1')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['ratio_to_base', 'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('product_units');
    }
};
