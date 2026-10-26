<?php

namespace App\Support\Guards;

/**
 * The guard families `ReconcileGuards` recomputes (DATABASE §3.1; ARCHITECTURE §7; GAP-031): those whose
 * formula the schema already fixes — the product's on hand, unusable and reserved quantities, a lot's quantity
 * at each place, and a lot's remaining quantity of its cost row. Each posting Task adds the families it owns,
 * with the formula of the semantics it builds (TASK-013; the discovery decided at its checkpoint).
 */
final class Families
{
    /** @return list<GuardFamily> */
    public static function all(): array
    {
        return [
            new Family(
                'stock_product_balances',
                ['product_id'],
                ['on_hand_qty', 'unusable_qty', 'reserved_qty'],
                <<<'SQL'
                    select keys.product_id,
                        coalesce(entries.on_hand_qty, 0) as on_hand_qty,
                        coalesce(entries.unusable_qty, 0) as unusable_qty,
                        coalesce(reserved.reserved_qty, 0) as reserved_qty
                    from (select product_id from stock_movement_entries union select product_id from reservations where state = 'ACTIVE') keys
                    left join (
                        select product_id, sum(qty_delta_base) as on_hand_qty,
                            sum(qty_delta_base) filter (where condition = 'UNUSABLE') as unusable_qty
                        from stock_movement_entries group by product_id
                    ) entries on entries.product_id = keys.product_id
                    left join (
                        select product_id, sum(remaining_qty_base) as reserved_qty
                        from reservations where state = 'ACTIVE' group by product_id
                    ) reserved on reserved.product_id = keys.product_id
                    SQL,
            ),
            new Family(
                'stock_lot_balances',
                ['lot_id', 'location_id', 'condition'],
                ['qty'],
                <<<'SQL'
                    select lot_id, location_id, condition, min(product_id) as product_id, sum(qty_delta_base) as qty
                    from stock_movement_entries where lot_id is not null
                    group by lot_id, location_id, condition
                    SQL,
                ['product_id'],
            ),
            new Family(
                'lot_cost_balances',
                ['lot_id'],
                ['remaining_qty_base'],
                <<<'SQL'
                    select lot_id, sum(qty_delta_base) as remaining_qty_base
                    from stock_movement_entries where lot_id is not null
                    group by lot_id
                    SQL,
                // The remaining cost is the cost semantics', registered by its posting Task; a missing row is reported, never invented.
                null,
            ),
        ];
    }

    /**
     * The families verified, never recomputed (DATABASE §3.1; DIR-095 (3); DIR-098 reading 6): `number_sequences`,
     * the authoritative counters of TASK-021 (LK-31), whose rule is that no number of a period issued here — a
     * version's, a project's, a purchase's or a tombstone's — stands at or above its counter's next number, and that
     * no such number lies in a period without a counter. Values below the counter that carry no row are not
     * differences: legacy numbers lie below a seed, and the Owner's raise after a restore skips the numbers the
     * papers hold (`DIR-083` OE-11).
     *
     * @return list<Verification>
     */
    public static function verifications(): array
    {
        return [
            new Verification(
                'number_sequences',
                ['company_id', 'sequence_type', 'period_key'],
                <<<'SQL'
                    select issued.company_id, issued.sequence_type, issued.period_key, issued.highest, counters.next_value
                    from (
                        select company_id, sequence_type, period_key, max(sequence_no) as highest
                        from (
                            select company_id, document_type_code as sequence_type, period_key, sequence_no from document_versions where sequence_no is not null
                            union all select company_id, 'PROJECT', period_key, sequence_no from projects
                            union all select company_id, 'PURCHASE', period_key, sequence_no from purchases
                            union all select company_id, sequence_type, period_key, sequence_no from retired_numbers
                        ) numbers
                        group by company_id, sequence_type, period_key
                    ) issued
                    left join number_sequences counters
                        on counters.company_id = issued.company_id and counters.sequence_type = issued.sequence_type and counters.period_key = issued.period_key
                    where counters.next_value is null or issued.highest >= counters.next_value
                    SQL,
            ),
        ];
    }

    public static function named(string $table): ?GuardFamily
    {
        foreach (self::all() as $family) {
            if ($family->table() === $table) {
                return $family;
            }
        }

        return null;
    }
}
