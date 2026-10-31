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
            // TASK-038 (C-61; BR-INV-08; DATABASE §5.5): a line guard's reserved quantity is its ACTIVE reservation's remaining
            // quantity — the product's RESERVED is Σ of them above. TASK-047 (C-30; L-39): its handed-over quantity is its
            // handover lines' less their reversal lines. A line with no guard is PRJ's to report.
            new Family(
                'project_line_balances',
                ['project_line_id'],
                ['reserved_qty_base', 'handed_over_qty_base'],
                <<<'SQL'
                    select b.project_line_id,
                        coalesce((select sum(r.remaining_qty_base) from reservations r where r.project_line_id = b.project_line_id and r.state = 'ACTIVE'), 0) as reserved_qty_base,
                        coalesce((select sum(case when h.reverses_line_id is null then h.qty_base else -h.qty_base end) from service_handover_lines h where h.project_line_id = b.project_line_id), 0) as handed_over_qty_base
                    from project_line_balances b
                    SQL,
                null,
            ),
            // TASK-049 (C-15; C-16; C-31; DATABASE §11.2): an invoice's guard is its live version's values and its active reductions; a project's
            // invoiced value is Σ its issued live versions'. A voided invoice's guard stands at nothing.
            new Family(
                'invoice_balances',
                ['invoice_id'],
                ['gross_value', 'sales_value', 'output_tax_value', 'applied_amount', 'fee_settled_amount', 'tax_settled_amount', 'written_off_amount'],
                <<<'SQL'
                    select b.invoice_id,
                        case when i.state = 'ISSUED' then v.gross_value else 0 end as gross_value,
                        case when i.state = 'ISSUED' then v.sales_value else 0 end as sales_value,
                        case when i.state = 'ISSUED' then v.output_tax_value else 0 end as output_tax_value,
                        coalesce((select sum(a.amount) from payment_applications a where a.invoice_id = b.invoice_id and a.state = 'ACTIVE'), 0) as applied_amount,
                        coalesce((select sum(s.amount) from deduction_settlements s where s.invoice_id = b.invoice_id and s.state = 'ACTIVE' and s.settlement_class = 'FEE'), 0) as fee_settled_amount,
                        coalesce((select sum(s.amount) from deduction_settlements s where s.invoice_id = b.invoice_id and s.state = 'ACTIVE' and s.settlement_class = 'TAX'), 0) as tax_settled_amount,
                        coalesce((select sum(w.amount) from receivable_writeoffs w where w.invoice_id = b.invoice_id), 0)
                            - coalesce((select sum(c.amount) from financial_contras c join receivable_writeoffs w on w.id = c.receivable_writeoff_id where w.invoice_id = b.invoice_id), 0) as written_off_amount
                    from invoice_balances b join invoices i on i.id = b.invoice_id join invoice_versions v on v.id = i.current_version_id
                    SQL,
                null,
            ),
            // TASK-051 (C-13; C-44; C-45; L-28; DATABASE §11.3): a source's guard is its recorded amount, its contras, its ACTIVE applications by target, its current claims and ACTIVE settlements; a payment
            // with no guard answers under its negated id. Shortfall and release are posted figures with no row of their own to sum: they stay the posting's, and the refund's fact carries their conservation (C-44).
            new Family(
                'application_source_balances',
                ['id'],
                ['gross_amount', 'contra_amount', 'invoice_applied_amount', 'refund_backed_amount', 'claimed_deductions_amount', 'settled_amount'],
                <<<'SQL'
                    select b.id,
                        coalesce(p.amount, c.amount) as gross_amount,
                        coalesce((select sum(x.amount) from financial_contras x where x.payment_id = b.payment_id or x.opening_customer_credit_id = b.opening_customer_credit_id), 0) as contra_amount,
                        coalesce((select sum(a.amount) from payment_applications a where (a.payment_id = b.payment_id or a.opening_customer_credit_id = b.opening_customer_credit_id) and a.state = 'ACTIVE' and a.target_kind = 'INVOICE'), 0) as invoice_applied_amount,
                        coalesce((select sum(a.amount) from payment_applications a where (a.payment_id = b.payment_id or a.opening_customer_credit_id = b.opening_customer_credit_id) and a.state = 'ACTIVE' and a.target_kind = 'REFUND'), 0) as refund_backed_amount,
                        coalesce((select sum(d.claimed_amount) from payment_claimed_deductions d where d.payment_id = b.payment_id and not exists (select 1 from payment_claimed_deductions n where n.supersedes_claimed_deduction_id = d.id)), 0) as claimed_deductions_amount,
                        coalesce((select sum(s.amount) from deduction_settlements s where s.payment_id = b.payment_id and s.state = 'ACTIVE'), 0) as settled_amount
                    from application_source_balances b left join payments p on p.id = b.payment_id left join opening_customer_credits c on c.id = b.opening_customer_credit_id
                    union all
                    select -p.id, p.amount, 0, 0, 0, 0, 0 from payments p where not exists (select 1 from application_source_balances b where b.payment_id = p.id)
                    SQL,
                null,
            ),
            // TASK-051 (C-19; L-22; DATABASE §4.10): a bank line's guard proves its amount while ACTIVE and holds its citing Cash-In, net of contras. An evidence document's guard is its citing Tasks' to register.
            new Family(
                'evidence_balances',
                ['id'],
                ['proven_amount', 'cited_amount'],
                <<<'SQL'
                    select g.id,
                        case when l.id is null then g.proven_amount when l.state = 'ACTIVE' then l.amount else 0 end as proven_amount,
                        case when l.id is null then g.cited_amount else
                            coalesce((select sum(p.amount) from payments p where p.bank_statement_line_id = l.id), 0)
                            - coalesce((select sum(x.amount) from financial_contras x join payments p on p.id = x.payment_id where p.bank_statement_line_id = l.id), 0)
                            + coalesce((select sum(r.amount) from other_cash_receipts r where r.bank_statement_line_id = l.id), 0)
                            - coalesce((select sum(x.amount) from financial_contras x join other_cash_receipts r on r.id = x.other_cash_receipt_id where r.bank_statement_line_id = l.id), 0)
                        end as cited_amount
                    from evidence_balances g left join bank_statement_lines l on l.id = g.bank_statement_line_id
                    union all
                    select -l.id, l.amount, 0 from bank_statement_lines l where not exists (select 1 from evidence_balances g where g.bank_statement_line_id = l.id)
                    SQL,
                null,
            ),
            // TASK-051 (C-44; C-45; BR-FIN-07 b): a money fact's contras, a refund's ACTIVE backing, a transfer-out's citing Cash-In net of its contras.
            new Family(
                'money_fact_balances',
                ['id'],
                ['contra_amount', 'backed_amount', 'transfer_cited_amount'],
                <<<'SQL'
                    select m.id,
                        coalesce((select sum(x.amount) from financial_contras x where x.disbursement_id = m.disbursement_id or x.other_cash_receipt_id = m.other_cash_receipt_id or x.expense_id = m.expense_id or x.deduction_settlement_id = m.deduction_settlement_id or x.receivable_writeoff_id = m.receivable_writeoff_id), 0) as contra_amount,
                        coalesce((select sum(a.amount) from payment_applications a where a.refund_disbursement_id = m.disbursement_id and a.state = 'ACTIVE'), 0) as backed_amount,
                        coalesce((select sum(p.amount) from payments p where p.transfer_from_disbursement_id = m.disbursement_id), 0)
                            - coalesce((select sum(x.amount) from financial_contras x join payments p on p.id = x.payment_id where p.transfer_from_disbursement_id = m.disbursement_id), 0)
                            + coalesce((select sum(r.amount) from other_cash_receipts r where r.transfer_from_disbursement_id = m.disbursement_id), 0)
                            - coalesce((select sum(x.amount) from financial_contras x join other_cash_receipts r on r.id = x.other_cash_receipt_id where r.transfer_from_disbursement_id = m.disbursement_id), 0) as transfer_cited_amount
                    from money_fact_balances m
                    SQL,
                null,
            ),
            new Family(
                'project_commercial_balances',
                ['project_id'],
                ['invoiced_sales_value'],
                <<<'SQL'
                    select b.project_id,
                        coalesce((select sum(v.sales_value) from invoices i join invoice_versions v on v.id = i.current_version_id where i.project_id = b.project_id and i.state = 'ISSUED' and i.origin = 'LIVE'), 0) as invoiced_sales_value
                    from project_commercial_balances b
                    SQL,
                null,
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
            // every CHARGE row naming it, on a lot or direct, a drop-ship return's contra aside: the units it moves into
            // their return lot's basis keep their cost allocated (§5.7) — and what is expensed of it. Every charge has
            // its guard from its recording, so a missing one is reported, never invented (C-61).
            new Family(
                'purchase_charge_balances',
                ['purchase_charge_id'],
                ['amount', 'allocated_amount', 'expensed_amount'],
                <<<'SQL'
                    select c.id as purchase_charge_id,
                        c.amount + coalesce((select sum(a.amount_delta) from purchase_line_adjustments a where a.purchase_charge_id = c.id and a.kind = 'CHARGE_CORRECTION'), 0) as amount,
                        coalesce((select sum(e.amount) from lot_cost_entries e where e.purchase_charge_id = c.id), 0)
                            + coalesce((select sum(t.amount) from cost_attributions t where t.purchase_charge_id = c.id
                                and not exists (select 1 from stock_restorations r where r.id = t.stock_restoration_id and r.kind = 'DROPSHIP_RETURN')), 0) as allocated_amount,
                        coalesce((select sum(x.amount) from expenses x where x.purchase_charge_id = c.id), 0) as expensed_amount
                    from purchase_charges c
                    SQL,
                null,
            ),
            // TASK-036 (C-21; DATABASE §6.1): each line's share of an attributable charge at its current version — what is
            // posted under that share, re-spread out of the line under that version, and expensed out of it, a drop-ship
            // return's contra aside as above.
            new Family(
                'purchase_charge_share_balances',
                ['purchase_charge_id', 'purchase_line_id'],
                ['allocation_version', 'share_amount', 'posted_amount', 'respread_out_amount', 'expensed_out_amount'],
                <<<'SQL'
                    select s.purchase_charge_id, s.purchase_line_id, s.allocation_version, s.share_amount,
                        coalesce((select sum(e.amount) from lot_cost_entries e where e.purchase_charge_line_share_id = s.id), 0)
                            + coalesce((select sum(t.amount) from cost_attributions t where t.purchase_charge_line_share_id = s.id
                                and not exists (select 1 from stock_restorations r where r.id = t.stock_restoration_id and r.kind = 'DROPSHIP_RETURN')), 0) as posted_amount,
                        coalesce((select sum(e.amount) from lot_cost_entries e where e.purchase_charge_id = s.purchase_charge_id
                            and e.respread_from_purchase_line_id = s.purchase_line_id and e.allocation_version = s.allocation_version), 0)
                            + coalesce((select sum(t.amount) from cost_attributions t where t.purchase_charge_id = s.purchase_charge_id
                            and t.respread_from_purchase_line_id = s.purchase_line_id and t.allocation_version = s.allocation_version
                                and not exists (select 1 from stock_restorations r where r.id = t.stock_restoration_id and r.kind = 'DROPSHIP_RETURN')), 0) as respread_out_amount,
                        coalesce((select sum(x.amount) from expenses x where x.purchase_charge_id = s.purchase_charge_id and x.purchase_line_id = s.purchase_line_id), 0) as expensed_out_amount
                    from purchase_charge_line_shares s
                    join purchase_charges c on c.id = s.purchase_charge_id and c.classification = 'ATTRIBUTABLE'
                    where s.allocation_version = (select max(v.allocation_version) from purchase_charge_line_shares v
                        where v.purchase_charge_id = s.purchase_charge_id and v.purchase_line_id = s.purchase_line_id)
                    SQL,
                null,
            ),
            // TASK-036 (C-57; DATABASE §6.1, §6.3): a purchase line's posted goods and non-creditable tax — net of every receipt
            // reversal, confirmation reversal and cost correction — are the cost rows of those components that name it: its
            // lots' entries, and the direct rows of its drop-ship and non-stock lines in every class, a drop-ship return's
            // contra aside — its units' cost moved into their return lot's basis, still the line's posted value (§5.7).
            new Family(
                'purchase_line_balances',
                ['purchase_line_id'],
                ['posted_goods_value', 'posted_noncreditable_tax_value'],
                <<<'SQL'
                    select b.purchase_line_id,
                        coalesce((select sum(e.amount) from lot_cost_entries e where e.purchase_line_id = b.purchase_line_id and e.cost_component = 'GOODS'), 0)
                            + coalesce((select sum(t.amount) from cost_attributions t left join dropship_confirmation_lines d on d.id = t.dropship_confirmation_line_id
                                where coalesce(d.purchase_line_id, t.purchase_line_id) = b.purchase_line_id and t.stock_consumption_id is null and t.cost_component = 'GOODS'
                                and not exists (select 1 from stock_restorations r where r.id = t.stock_restoration_id and r.kind = 'DROPSHIP_RETURN')), 0) as posted_goods_value,
                        coalesce((select sum(e.amount) from lot_cost_entries e where e.purchase_line_id = b.purchase_line_id and e.cost_component = 'NONCREDITABLE_TAX'), 0)
                            + coalesce((select sum(t.amount) from cost_attributions t left join dropship_confirmation_lines d on d.id = t.dropship_confirmation_line_id
                                where coalesce(d.purchase_line_id, t.purchase_line_id) = b.purchase_line_id and t.stock_consumption_id is null and t.cost_component = 'NONCREDITABLE_TAX'
                                and not exists (select 1 from stock_restorations r where r.id = t.stock_restoration_id and r.kind = 'DROPSHIP_RETURN')), 0) as posted_noncreditable_tax_value
                    from purchase_line_balances b
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
