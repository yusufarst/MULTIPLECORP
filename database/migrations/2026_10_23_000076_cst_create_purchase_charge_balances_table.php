<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.8 `purchase_charge_balances` (GB): a charge's conservation, posted by CostPosting (§3.1) — its
// current amount (the recorded amount and its CHARGE_CORRECTION deltas) is allocated, pending or expensed,
// any part allocated or expensed, never both (AX-29; L-47; C-21); a correction moves it with the source
// bill's evidence guard (AX-34).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_charge_balances', function (Blueprint $table) {
            $table->bigInteger('purchase_charge_id')->primary();
            Columns::money($table, 'amount');
            Columns::money($table, 'allocated_amount')->default(0);
            Columns::money($table, 'expensed_amount')->default(0);
            Columns::recordedAt($table);
            $table->foreign('purchase_charge_id')->references('id')->on('purchase_charges')->restrictOnDelete();
        });

        TableRules::for('purchase_charge_balances')
            ->check('purchase_charge_balances_c21_conservation', 'allocated_amount >= 0 and expensed_amount >= 0 and allocated_amount + expensed_amount <= amount')
            ->grant('select, insert')
            ->grantUpdate(['amount', 'allocated_amount', 'expensed_amount']);
    }

    public function down(): void
    {
        Schema::drop('purchase_charge_balances');
    }
};
