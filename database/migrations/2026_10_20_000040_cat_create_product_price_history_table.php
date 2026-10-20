<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.4 `product_price_history` (AF): the default prices and their history (CAP-03); the current
// default of a kind is its latest row, and transactions snapshot their own prices (§14).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_history', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'product_id', 'products');
            $table->text('price_kind');
            Columns::money($table, 'amount');
            $table->date('effective_from');
            $table->text('reason')->nullable();
            Columns::commandIdentity($table, 'product_price_history');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('product_price_history')
            ->codes('product_price_history_kind_code', 'price_kind', ['PURCHASE_DEFAULT', 'SELLING_DEFAULT', 'SPJ_REFERENCE'])
            ->check('product_price_history_amount_not_negative', 'amount >= 0')
            // The current default of a kind: the latest row.
            ->index('product_price_history_current', 'product_id, price_kind, effective_from, id')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('product_price_history');
    }
};
