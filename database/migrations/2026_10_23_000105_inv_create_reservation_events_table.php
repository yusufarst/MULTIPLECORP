<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `reservation_events` (AF) and §5.5: a reservation's history, its remaining quantity their sum
// (§3.1), each delta at the product's scale (§1; C-09); a CUT names the supply-reducing movement that forced
// it (SF-RSV-CUT; BR-RSV-04; C-06), a consumption the dispatch line that drew it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_events', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'reservation_id', 'reservations');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            $table->text('kind');
            Columns::quantity($table, 'qty_delta_base');
            $table->text('reason')->nullable();
            Columns::reference($table, 'dispatch_line_id', 'dispatch_lines')->nullable();
            Columns::reference($table, 'cut_by_movement_id', 'stock_movements')->nullable();
            Columns::commandIdentity($table, 'reservation_events');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('reservation_events')
            ->foreign('reservation_events_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->codes('reservation_events_kind_code', 'kind', ['CREATE', 'INCREASE', 'DECREASE', 'RELEASE', 'CONSUME', 'CUT'])
            ->check('reservation_events_c09_quantity', 'qty_delta_base = round(qty_delta_base, quantity_scale)')
            ->check('reservation_events_c06_cut_names_movement', "(kind = 'CUT') = (cut_by_movement_id is not null)")
            ->index('reservation_events_reservation', 'reservation_id')
            ->index('reservation_events_dispatch_line', 'dispatch_line_id', 'dispatch_line_id is not null')
            ->index('reservation_events_cut_by_movement', 'cut_by_movement_id', 'cut_by_movement_id is not null')
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('reservation_events');
    }
};
