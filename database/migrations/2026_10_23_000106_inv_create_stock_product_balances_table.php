<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_product_balances` (GB) and §5.2: AVAILABLE = ON HAND − RESERVED − UNUSABLE ≥ 0,
// enforced by PostgreSQL on this guard row at every commit, the posting path keeping it equal to the ledger
// and the reservations (BR-INV-01; BR-RSV-01; C-04, C-06; DB+APP+DQ). Its `version` is a count's basis.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_product_balances', function (Blueprint $table) {
            $table->bigInteger('product_id')->primary();
            Columns::quantity($table, 'on_hand_qty')->default(0);
            Columns::quantity($table, 'unusable_qty')->default(0);
            Columns::quantity($table, 'reserved_qty')->default(0);
            $table->bigInteger('version')->default(0);
            Columns::recordedAt($table);
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });

        TableRules::for('stock_product_balances')
            ->check('stock_product_balances_c04_available', 'unusable_qty >= 0 and reserved_qty >= 0 and unusable_qty <= on_hand_qty and reserved_qty + unusable_qty <= on_hand_qty')
            ->grant('select, insert')
            ->grantUpdate(['on_hand_qty', 'unusable_qty', 'reserved_qty', 'version']);
    }

    public function down(): void
    {
        Schema::drop('stock_product_balances');
    }
};
