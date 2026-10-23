<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The foreign keys and triggers of earlier Tasks' columns on this Task's tables, added expand-only now that
// the tables exist: DIR-088's `correction_cases.source_lot_id`, an intentional cross-company link (§15);
// DIR-089 (1)'s documents' dispatch and receiving movements in the document's company, and evidence links'
// movement and pending case — company evidence to its company's movement, pool evidence only to a pool
// movement (§4.10; C-58); DIR-090 (1)'s lot cost entries' and balances' lot — the entry's with its source
// company —, restorations, an attribution's consumption with its lot and source (C-28), and §4.8's triggers
// holding a lot's basis kind to its acquisition and a lot draw's bearer to its consumption's, a
// RETURN_SETTLEMENT row aside (§6.5); DIR-090 (5)'s receipts in a purchase line's first effect (L-23).
return new class extends Migration
{
    /** @var array<string, array{string, list<string>, string, list<string>}> */
    private const KEYS = [
        'correction_cases_source_lot_foreign' => ['correction_cases', ['source_lot_id'], 'inventory_lots', ['id']],
        'documents_c32_dispatch_movement_foreign' => ['documents', ['company_id', 'dispatch_movement_id'], 'stock_movements', ['company_id', 'id']],
        'documents_c32_receiving_movement_foreign' => ['documents', ['company_id', 'receiving_movement_id'], 'stock_movements', ['company_id', 'id']],
        'evidence_links_stock_movement_foreign' => ['evidence_links', ['stock_movement_id'], 'stock_movements', ['id']],
        'evidence_links_c32_stock_movement_foreign' => ['evidence_links', ['company_id', 'stock_movement_id'], 'stock_movements', ['company_id', 'id']],
        'evidence_links_unattributed_loss_case_foreign' => ['evidence_links', ['unattributed_loss_case_id'], 'unattributed_loss_cases', ['id']],
        'lot_cost_entries_c28_lot_foreign' => ['lot_cost_entries', ['lot_id', 'source_company_id'], 'inventory_lots', ['id', 'source_company_id']],
        'lot_cost_entries_restoration_foreign' => ['lot_cost_entries', ['stock_restoration_id'], 'stock_restorations', ['id']],
        'lot_cost_balances_lot_foreign' => ['lot_cost_balances', ['lot_id'], 'inventory_lots', ['id']],
        'cost_attributions_c28_consumption_foreign' => ['cost_attributions', ['stock_consumption_id', 'lot_id', 'source_company_id'], 'stock_consumptions', ['id', 'lot_id', 'source_company_id']],
        'cost_attributions_restoration_foreign' => ['cost_attributions', ['stock_restoration_id'], 'stock_restorations', ['id']],
    ];

    /** @var array<string, array{string, string, string}> trigger => [table, condition, refusal] */
    private const TRIGGERS = [
        'evidence_links_c58_pool_movement' => ['evidence_links', 'new.stock_movement_id is not null and new.company_id is null', <<<'SQL'
            exists (select from stock_movements m where m.id = new.stock_movement_id and m.company_id is not null)
            SQL],
        'lot_cost_entries_basis_of_acquisition' => ['lot_cost_entries', "new.entry_kind in ('GOODS', 'OPENING_COST', 'SURPLUS_FLAGGED_BASIS', 'DROPSHIP_RETURN_BASIS', 'REPLACEMENT_BASIS')", <<<'SQL'
            not exists (
                select from inventory_lots l left join receiving_lines r on r.id = l.receiving_line_id
                where l.id = new.lot_id and case new.entry_kind
                    when 'GOODS' then l.acquisition_kind = 'RECEIPT' and r.receipt_kind <> 'REPLACEMENT'
                    when 'REPLACEMENT_BASIS' then l.acquisition_kind = 'RECEIPT' and r.receipt_kind = 'REPLACEMENT'
                    when 'OPENING_COST' then l.acquisition_kind = 'OPENING'
                    when 'DROPSHIP_RETURN_BASIS' then l.acquisition_kind = 'DROPSHIP_RETURN'
                    else l.acquisition_kind = 'SURPLUS'
                end
            )
            SQL],
        'cost_attributions_c28_bearer_of_consumption' => ['cost_attributions', "new.stock_consumption_id is not null and new.cause <> 'RETURN_SETTLEMENT'", <<<'SQL'
            not exists (
                select from stock_consumptions c where c.id = new.stock_consumption_id
                    and c.bearer_company_id = new.bearer_company_id and c.bearer_project_id is not distinct from new.bearer_project_id
            )
            SQL],
    ];

    public function up(): void
    {
        foreach (self::KEYS as $name => [$table, $columns, $references, $referenced]) {
            TableRules::for($table)->foreign($name, $columns, $references, $referenced);
        }

        TableRules::for('correction_cases')->index('correction_cases_source_lot', 'source_lot_id', 'source_lot_id is not null');
        TableRules::for('lot_cost_entries')->index('lot_cost_entries_restoration', 'stock_restoration_id', 'stock_restoration_id is not null');
        TableRules::for('cost_attributions')->index('cost_attributions_restoration', 'stock_restoration_id', 'stock_restoration_id is not null');

        foreach (self::TRIGGERS as $name => [$table, $condition, $refusal]) {
            DB::unprepared(<<<SQL
                create or replace function {$name}() returns trigger
                language plpgsql as \$\$
                begin
                    if {$refusal} then
                        raise exception 'A reference is held to its cause (DATABASE §4.7, §4.8, §4.10; C-28, C-58).'
                            using errcode = 'check_violation', constraint = '{$name}';
                    end if;

                    return null;
                end
                \$\$;

                create constraint trigger {$name}
                    after insert on {$table}
                    for each row when ({$condition})
                    execute function {$name}();
                SQL);
        }

        DB::unprepared(<<<'SQL'
            create or replace function purchase_line_has_effect(line bigint) returns boolean
            language plpgsql stable as $$
            begin
                return exists (select from receiving_lines where purchase_line_id = line)
                    or exists (select from lot_cost_entries where purchase_line_id = line)
                    or exists (select from cost_attributions where purchase_line_id = line)
                    or exists (select from purchase_line_closures where purchase_line_id = line)
                    or exists (select from purchase_line_adjustments where purchase_line_id = line)
                    or exists (
                        select from purchase_line_balances
                        where purchase_line_id = line and (normal_received_net_qty_base > 0 or closed_qty_base > 0
                            or dropship_confirmed_net_qty_base > 0 or replacement_received_net_qty_base > 0
                            or posted_goods_value > 0 or posted_noncreditable_tax_value > 0)
                    );
            end
            $$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function purchase_line_has_effect(line bigint) returns boolean
            language plpgsql stable as $$
            begin
                return exists (select from lot_cost_entries where purchase_line_id = line)
                    or exists (select from cost_attributions where purchase_line_id = line)
                    or exists (select from purchase_line_closures where purchase_line_id = line)
                    or exists (select from purchase_line_adjustments where purchase_line_id = line)
                    or exists (
                        select from purchase_line_balances
                        where purchase_line_id = line and (normal_received_net_qty_base > 0 or closed_qty_base > 0
                            or dropship_confirmed_net_qty_base > 0 or replacement_received_net_qty_base > 0
                            or posted_goods_value > 0 or posted_noncreditable_tax_value > 0)
                    );
            end
            $$;
            SQL);

        foreach (self::TRIGGERS as $name => [$table]) {
            DB::unprepared("drop trigger {$name} on {$table}; drop function {$name}()");
        }

        foreach (['correction_cases_source_lot', 'lot_cost_entries_restoration', 'cost_attributions_restoration'] as $index) {
            DB::statement("drop index {$index}");
        }

        foreach (array_reverse(self::KEYS) as $name => [$table]) {
            DB::statement("alter table {$table} drop constraint {$name}");
        }
    }
};
