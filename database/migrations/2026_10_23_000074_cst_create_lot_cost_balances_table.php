<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.8 `lot_cost_balances` (GB): a lot's current net cost, posted by CostPosting (§3.1) — never
// negative, and no cost left once no quantity is, so the zeroing draw takes exactly the remaining cost
// (BR-FIN-13; C-26). The lot is TASK-008's, which keys it (DIR-088's rule).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_cost_balances', function (Blueprint $table) {
            $table->bigInteger('lot_id')->primary();
            Columns::quantity($table, 'remaining_qty_base')->default(0);
            Columns::money($table, 'remaining_cost')->default(0);
            Columns::recordedAt($table);
        });

        TableRules::for('lot_cost_balances')
            ->check('lot_cost_balances_c26_conservation', 'remaining_qty_base >= 0 and remaining_cost >= 0 and (remaining_qty_base > 0 or remaining_cost = 0)')
            ->grant('select, insert')
            ->grantUpdate(['remaining_qty_base', 'remaining_cost']);
    }

    public function down(): void
    {
        Schema::drop('lot_cost_balances');
    }
};
