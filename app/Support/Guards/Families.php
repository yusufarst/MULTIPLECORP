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
            // TASK-036 (C-26; DATABASE §6.2): a lot's remaining cost is Σ its cost entries less its net draws — every cost row
            // keyed to it, contras and corrections included — beside its remaining quantity. A missing row is reported, never invented.
            new Family(
                'lot_cost_balances',
                ['lot_id'],
                ['remaining_qty_base', 'remaining_cost'],
                <<<'SQL'
                    select keys.lot_id,
                        coalesce(quantities.qty, 0) as remaining_qty_base,
                        coalesce(entries.cost, 0) - coalesce(draws.drawn, 0) as remaining_cost
                    from (
                        select lot_id from stock_movement_entries where lot_id is not null
                        union select lot_id from lot_cost_entries
                    ) keys
                    left join (
                        select lot_id, sum(qty_delta_base) as qty from stock_movement_entries where lot_id is not null group by lot_id
                    ) quantities on quantities.lot_id = keys.lot_id
                    left join (select lot_id, sum(amount) as cost from lot_cost_entries group by lot_id) entries on entries.lot_id = keys.lot_id
                    left join (
                        select lot_id, sum(amount) as drawn from cost_attributions where lot_id is not null group by lot_id
                    ) draws on draws.lot_id = keys.lot_id
                    SQL,
                null,
            ),
            // TASK-036 (C-21; L-47): a charge's current amount — recorded plus its corrections —, what is allocated of it —
            // every CHARGE row naming it, on a lot or direct — and what is expensed of it.
            new Family(
                'purchase_charge_balances',
                ['purchase_charge_id'],
                ['amount', 'allocated_amount', 'expensed_amount'],
                <<<'SQL'
                    select c.id as purchase_charge_id,
                        c.amount + coalesce((select sum(a.amount_delta) from purchase_line_adjustments a where a.purchase_charge_id = c.id and a.kind = 'CHARGE_CORRECTION'), 0) as amount,
                        coalesce((select sum(e.amount) from lot_cost_entries e where e.purchase_charge_id = c.id), 0)
                            + coalesce((select sum(t.amount) from cost_attributions t where t.purchase_charge_id = c.id), 0) as allocated_amount,
                        coalesce((select sum(x.amount) from expenses x where x.purchase_charge_id = c.id), 0) as expensed_amount
                    from purchase_charges c
                    where exists (select 1 from purchase_charge_balances b where b.purchase_charge_id = c.id)
                    SQL,
                null,
            ),
            // TASK-036 (C-21; DATABASE §6.1): each line's share of a charge at its current version — what is posted under
            // that share, re-spread out of the line under that version, and expensed out of it.
            new Family(
                'purchase_charge_share_balances',
                ['purchase_charge_id', 'purchase_line_id'],
                ['allocation_version', 'share_amount', 'posted_amount', 'respread_out_amount', 'expensed_out_amount'],
                <<<'SQL'
                    select s.purchase_charge_id, s.purchase_line_id, s.allocation_version, s.share_amount,
                        coalesce((select sum(e.amount) from lot_cost_entries e where e.purchase_charge_line_share_id = s.id), 0)
                            + coalesce((select sum(t.amount) from cost_attributions t where t.purchase_charge_line_share_id = s.id), 0) as posted_amount,
                        coalesce((select sum(e.amount) from lot_cost_entries e where e.purchase_charge_id = s.purchase_charge_id
                            and e.respread_from_purchase_line_id = s.purchase_line_id and e.allocation_version = s.allocation_version), 0)
                            + coalesce((select sum(t.amount) from cost_attributions t where t.purchase_charge_id = s.purchase_charge_id
                            and t.respread_from_purchase_line_id = s.purchase_line_id and t.allocation_version = s.allocation_version), 0) as respread_out_amount,
                        coalesce((select sum(x.amount) from expenses x where x.purchase_charge_id = s.purchase_charge_id and x.purchase_line_id = s.purchase_line_id), 0) as expensed_out_amount
                    from purchase_charge_line_shares s
                    where s.allocation_version = (select max(v.allocation_version) from purchase_charge_line_shares v
                        where v.purchase_charge_id = s.purchase_charge_id and v.purchase_line_id = s.purchase_line_id)
                    and exists (select 1 from purchase_charge_share_balances b where b.purchase_charge_id = s.purchase_charge_id)
                    SQL,
                null,
            ),
            // TASK-031 (C-46; C-61): a correction case's counters whose formula §3.1 fixes over the schema — the refund
            // shortfalls its money facts carry, its RESIDUAL settlements, its returned units' SUPPLIER_RETURN cost without
            // the RETURN_SETTLEMENT re-attribution, and that settlement's LOSS. `refunded_value` and `replaced_value` are
            // their posting Tasks' to register with the sign and corrections they build. A case is never invented.
            new Family(
                'correction_cases',
                ['id'],
                ['unbacked_refund_amount', 'residual_settlement_amount', 'returned_cost', 'unrecovered_value'],
                <<<'SQL'
                    select id,
                        sum(unbacked_refund_amount) as unbacked_refund_amount,
                        sum(residual_settlement_amount) as residual_settlement_amount,
                        sum(returned_cost) as returned_cost,
                        sum(unrecovered_value) as unrecovered_value
                    from (
                        select residual_case_id as id, shortfall_amount as unbacked_refund_amount, 0 as residual_settlement_amount, 0 as returned_cost, 0 as unrecovered_value
                        from money_fact_balances where fact_kind = 'REFUND' and residual_case_id is not null
                        union all
                        select correction_case_id, 0, amount, 0, 0
                        from deduction_settlements where state = 'RESIDUAL' and correction_case_id is not null
                        union all
                        select correction_case_id, 0, 0, amount, 0
                        from cost_attributions where cost_class = 'SUPPLIER_RETURN' and cause <> 'RETURN_SETTLEMENT' and correction_case_id is not null
                        union all
                        select correction_case_id, 0, 0, 0, amount
                        from cost_attributions where cost_class = 'LOSS' and cause = 'RETURN_SETTLEMENT' and correction_case_id is not null
                    ) facts
                    group by id
                    SQL,
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
