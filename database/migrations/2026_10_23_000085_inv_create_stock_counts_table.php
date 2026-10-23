<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_counts` (SR) and §5.6: an opname session over a scope — a location, a condition and a
// product set —, its typed counted basis in `stock_count_scopes`; the movements that apply it cite it, one
// application posting several (BR-INV-07; L-14). A count changed by a movement in its scope is STALE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            Columns::reference($table, 'location_id', 'locations');
            $table->text('condition');
            // The product set of the scope; NULL or empty for every product of the location and condition (DIR-091 (4)).
            $table->rawColumn('product_ids', 'bigint[]')->nullable();
            $table->text('state')->default('OPEN');
            Columns::timestamp($table, 'started_at')->useCurrent();
            Columns::commandIdentity($table, 'stock_counts');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('stock_counts')
            ->codes('stock_counts_condition_code', 'condition', ['USABLE', 'UNUSABLE'])
            ->codes('stock_counts_state_code', 'state', ['OPEN', 'APPLIED', 'STALE', 'CANCELLED'])
            // IX-14: the STALE counts (QS-18).
            ->index('stock_counts_ix14_stale', 'id', "state = 'STALE'")
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['state', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('stock_counts');
    }
};
