<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_consumption_balances` (GB) and §3.1: what is restored, reclassified and substituted of
// a warehouse consumption or a drop-ship confirmation line — an exclusive arc —, never more than was
// consumed (AX-10; CM-07/14/15/36; C-27). The drop-ship line is TASK-009's, which keys it.
return new class extends Migration
{
    private const PARTS = ['restored_qty_base', 'reclassified_qty_base', 'substituted_qty_base'];

    public function up(): void
    {
        Schema::create('stock_consumption_balances', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'stock_consumption_id', 'stock_consumptions')->nullable()->unique();
            $table->bigInteger('dropship_confirmation_line_id')->nullable()->unique();
            Columns::quantity($table, 'qty_base');

            foreach (self::PARTS as $part) {
                Columns::quantity($table, $part)->default(0);
            }

            Columns::recordedAt($table);
        });

        TableRules::for('stock_consumption_balances')
            ->check('stock_consumption_balances_one_source', 'num_nonnulls(stock_consumption_id, dropship_confirmation_line_id) = 1')
            ->check('stock_consumption_balances_c27_cap', 'qty_base > 0 and restored_qty_base >= 0 and reclassified_qty_base >= 0 and substituted_qty_base >= 0 and restored_qty_base + reclassified_qty_base + substituted_qty_base <= qty_base')
            ->grant('select, insert')
            ->grantUpdate(self::PARTS);
    }

    public function down(): void
    {
        Schema::drop('stock_consumption_balances');
    }
};
