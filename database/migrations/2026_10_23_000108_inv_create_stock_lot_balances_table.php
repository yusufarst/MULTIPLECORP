<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_lot_balances` (GB) and §5.4: a lot's remainder — its claims — at a location in a
// condition, quantity only (S1; L-13), never negative (C-03), its product the lot's.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lot_balances', function (Blueprint $table) {
            $table->bigInteger('lot_id');
            Columns::reference($table, 'location_id', 'locations');
            $table->text('condition');
            $table->bigInteger('product_id');
            Columns::quantity($table, 'qty')->default(0);
            Columns::recordedAt($table);
            $table->primary(['lot_id', 'location_id', 'condition']);
        });

        TableRules::for('stock_lot_balances')
            ->foreign('stock_lot_balances_lot_foreign', ['lot_id', 'product_id'], 'inventory_lots', ['id', 'product_id'])
            ->codes('stock_lot_balances_condition_code', 'condition', ['USABLE', 'UNUSABLE'])
            ->check('stock_lot_balances_c03_not_negative', 'qty >= 0')
            // §20: the oldest-first picking suggestion over lots that hold stock.
            ->index('stock_lot_balances_picking', 'product_id, location_id', 'qty > 0')
            ->grant('select, insert')
            ->grantUpdate(['qty']);
    }

    public function down(): void
    {
        Schema::drop('stock_lot_balances');
    }
};
