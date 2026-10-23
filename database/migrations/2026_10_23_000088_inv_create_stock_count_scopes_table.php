<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `stock_count_scopes` (SN) and §5.6: the typed counted basis — each scope's guard `version`
// when the count opened and its quantity then —, inserted only in the transaction that opens the count,
// which the trigger reads as the count's start, its column's default, being the transaction's own instant
// (`now()`). Applying the count compares each `basis_version` with the live scope version: a difference
// makes it STALE (L-14; GAP-009; C-33). The quantity holds its product's scale (§1).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_count_scopes', function (Blueprint $table) {
            Columns::reference($table, 'stock_count_id', 'stock_counts');
            $table->bigInteger('product_id');
            $table->smallInteger('quantity_scale');
            Columns::reference($table, 'location_id', 'locations');
            $table->text('condition');
            $table->bigInteger('basis_version');
            Columns::quantity($table, 'system_qty_at_start');
            Columns::recordedAt($table);
            $table->primary(['stock_count_id', 'product_id', 'location_id', 'condition']);
        });

        TableRules::for('stock_count_scopes')
            ->foreign('stock_count_scopes_c09_product_scale_foreign', ['product_id', 'quantity_scale'], 'products', ['id', 'quantity_scale'])
            ->codes('stock_count_scopes_condition_code', 'condition', ['USABLE', 'UNUSABLE'])
            ->check('stock_count_scopes_c09_scale', 'basis_version >= 0 and system_qty_at_start >= 0 and system_qty_at_start = round(system_qty_at_start, quantity_scale)')
            ->grant('select, insert');

        DB::unprepared(<<<'SQL'
            create or replace function stock_count_scopes_c33_opened_with_count() returns trigger
            language plpgsql as $$
            begin
                if not exists (select from stock_counts c where c.id = new.stock_count_id and c.started_at = now()) then
                    raise exception 'A count''s basis is written only in the transaction that opens it (DATABASE §4.7; L-14).'
                        using errcode = 'check_violation', constraint = 'stock_count_scopes_c33_opened_with_count';
                end if;

                return new;
            end
            $$;

            create trigger stock_count_scopes_c33_opened_with_count
                before insert on stock_count_scopes
                for each row execute function stock_count_scopes_c33_opened_with_count();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('stock_count_scopes');
        DB::unprepared('drop function if exists stock_count_scopes_c33_opened_with_count()');
    }
};
