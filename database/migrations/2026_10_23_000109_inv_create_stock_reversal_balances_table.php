<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_reversal_balances` (GB) and §5.3: what MOVEMENT_REVERSALs have undone of a movement's
// net at a lot, location and condition, never more than the original (CM-28/33/37; AX-36; C-55).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reversal_balances', function (Blueprint $table) {
            Columns::reference($table, 'reversed_movement_id', 'stock_movements');
            Columns::reference($table, 'lot_id', 'inventory_lots');
            Columns::reference($table, 'location_id', 'locations');
            $table->text('condition');
            Columns::quantity($table, 'original_qty_base');
            Columns::quantity($table, 'reversed_qty_base')->default(0);
            Columns::recordedAt($table);
            $table->primary(['reversed_movement_id', 'lot_id', 'location_id', 'condition']);
        });

        TableRules::for('stock_reversal_balances')
            ->codes('stock_reversal_balances_condition_code', 'condition', ['USABLE', 'UNUSABLE'])
            ->check('stock_reversal_balances_c55_cap', 'reversed_qty_base >= 0 and reversed_qty_base <= original_qty_base')
            ->grant('select, insert')
            ->grantUpdate(['reversed_qty_base']);
    }

    public function down(): void
    {
        Schema::drop('stock_reversal_balances');
    }
};
