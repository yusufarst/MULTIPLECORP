<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `serial_units` (SR, the current state of the ledger) and §8: one physical identity per
// (product, normalized serial) for its whole life (BR-INV-09; C-02) — in stock exactly when it has a lot,
// location and condition, never twice. Its recorded serial text is never edited: the runtime updates only
// its state columns. `last_entry_id` is keyed by 2026_10_23_000103.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_units', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'product_id', 'products');
            $table->text('serial_number');
            $table->text('serial_key');
            $table->text('state');
            $table->bigInteger('current_lot_id')->nullable();
            Columns::reference($table, 'current_location_id', 'locations')->nullable();
            $table->text('current_condition')->nullable();
            $table->bigInteger('last_entry_id')->nullable();
            Columns::commandIdentity($table, 'serial_units');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('serial_units')
            ->unique('serial_units_c02_one_identity', ['product_id', 'serial_key'])
            ->foreign('serial_units_current_lot_foreign', ['current_lot_id', 'product_id'], 'inventory_lots', ['id', 'product_id'])
            ->codes('serial_units_state_code', 'state', ['IN_STOCK', 'DISPATCHED', 'DROPSHIPPED', 'RETURNED_TO_SUPPLIER', 'LOST', 'NEVER_RECEIVED'])
            ->codes('serial_units_condition_code', 'current_condition', ['USABLE', 'UNUSABLE'], nullable: true)
            ->check('serial_units_c02_in_stock', "(state = 'IN_STOCK') = (current_lot_id is not null and current_location_id is not null and current_condition is not null)")
            // §20: a serial scan with the product unknown.
            ->index('serial_units_serial_key', 'serial_key')
            ->index('serial_units_current_lot', 'current_lot_id', 'current_lot_id is not null')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['state', 'current_lot_id', 'current_location_id', 'current_condition', 'last_entry_id']);
    }

    public function down(): void
    {
        Schema::drop('serial_units');
    }
};
