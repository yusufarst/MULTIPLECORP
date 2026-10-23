<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// DIR-091 and APPR-020, expand-only over existing columns: each row held to its parent's product by a
// composite key — a restoration and an entry to their consumption or restoration, a lot to its receiving
// line, a reservation event to its reservation (DIR-091 (3)); a count's scope rows within its location,
// condition and product set, whose every member is a distinct goods product of the catalogue, which is
// shared by every company (DIR-091 (3), (4); §4.4); a restoration's correction case owned by its source or
// by its consumption's bearer, as on consumptions (DIR-091 (2); §17). The drop-ship side of these keys is
// TASK-009's, with its tables (DIR-091 (1)).
return new class extends Migration
{
    /** @var array<string, array{string, list<string>}> the parent keys the composite keys reference */
    private const PARENT_KEYS = [
        'stock_consumptions_product_key' => ['stock_consumptions', ['id', 'product_id']],
        'stock_restorations_product_key' => ['stock_restorations', ['id', 'product_id']],
        'receiving_lines_product_key' => ['receiving_lines', ['id', 'product_id']],
        'reservations_product_key' => ['reservations', ['id', 'product_id']],
    ];

    /** @var array<string, array{string, list<string>, string, list<string>}> */
    private const KEYS = [
        'stock_restorations_consumption_product_foreign' => ['stock_restorations', ['stock_consumption_id', 'product_id'], 'stock_consumptions', ['id', 'product_id']],
        'stock_movement_entries_consumption_product_foreign' => ['stock_movement_entries', ['stock_consumption_id', 'product_id'], 'stock_consumptions', ['id', 'product_id']],
        'stock_movement_entries_restoration_product_foreign' => ['stock_movement_entries', ['stock_restoration_id', 'product_id'], 'stock_restorations', ['id', 'product_id']],
        'inventory_lots_receiving_product_foreign' => ['inventory_lots', ['receiving_line_id', 'product_id'], 'receiving_lines', ['id', 'product_id']],
        'reservation_events_reservation_product_foreign' => ['reservation_events', ['reservation_id', 'product_id'], 'reservations', ['id', 'product_id']],
    ];

    /** @var array<string, array{string, string, string, string}> trigger => [table, event, condition, refusal] */
    private const TRIGGERS = [
        'stock_counts_product_set' => ['stock_counts', 'insert or update of product_ids', 'coalesce(cardinality(new.product_ids), 0) > 0', <<<'SQL'
            array_position(new.product_ids, null) is not null
                or cardinality(new.product_ids) <> (select count(distinct p) from unnest(new.product_ids) p)
                or exists (select from unnest(new.product_ids) p where not exists (select from products x where x.id = p and x.kind = 'GOODS'))
            SQL],
        'stock_count_scopes_within_count' => ['stock_count_scopes', 'insert', 'true', <<<'SQL'
            not exists (
                select from stock_counts c where c.id = new.stock_count_id
                    and c.location_id = new.location_id and c.condition = new.condition
                    and (coalesce(cardinality(c.product_ids), 0) = 0 or new.product_id = any (c.product_ids))
            )
            SQL],
        'stock_restorations_case_side' => ['stock_restorations', 'insert', 'new.case_company_id is not null and new.case_company_id <> new.source_company_id', <<<'SQL'
            not exists (select from stock_consumptions c where c.id = new.stock_consumption_id and c.bearer_company_id = new.case_company_id)
            SQL],
    ];

    public function up(): void
    {
        foreach (self::PARENT_KEYS as $name => [$table, $columns]) {
            TableRules::for($table)->unique($name, $columns);
        }

        foreach (self::KEYS as $name => [$table, $columns, $references, $referenced]) {
            TableRules::for($table)->foreign($name, $columns, $references, $referenced);
        }

        foreach (self::TRIGGERS as $name => [$table, $event, $condition, $refusal]) {
            DB::unprepared(<<<SQL
                create or replace function {$name}() returns trigger
                language plpgsql as \$\$
                begin
                    if {$refusal} then
                        raise exception 'A row is held to its parent''s product, scope and case side (DATABASE §4.7, §17; DIR-091).'
                            using errcode = 'check_violation', constraint = '{$name}';
                    end if;

                    return null;
                end
                \$\$;

                create constraint trigger {$name}
                    after {$event} on {$table}
                    for each row when ({$condition})
                    execute function {$name}();
                SQL);
        }
    }

    public function down(): void
    {
        foreach (self::TRIGGERS as $name => [$table]) {
            DB::unprepared("drop trigger {$name} on {$table}; drop function {$name}()");
        }

        foreach (self::KEYS as $name => [$table]) {
            DB::statement("alter table {$table} drop constraint {$name}");
        }

        foreach (self::PARENT_KEYS as $name => [$table]) {
            DB::statement("alter table {$table} drop constraint {$name}");
        }
    }
};
