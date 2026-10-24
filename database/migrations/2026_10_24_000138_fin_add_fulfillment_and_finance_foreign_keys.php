<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The foreign keys of earlier Tasks' columns on this Task's tables, added expand-only now that the tables
// exist: DIR-089 (1)'s fourteen — a document's drop-ship confirmation, invoice, payment, delivery and
// handover, a version's invoice version and an evidence link's FUL and FIN records —, each in the
// company-scoped form of §15 (C-32); DIR-090 (1)'s direct attribution of a drop-ship line, borne by that
// line's company but a RETURN_SETTLEMENT, by trigger as TASK-007's for a purchase line, and a fee expense's
// settlement of its company; DIR-091 (1)'s drop-ship side of TASK-008's
// columns — the consumption cap's line, and a restoration's line with its unit snapshot, its confirmation's
// company and its product (§4.7; DIR-091 (3)). DIR-090 (5): a drop-ship confirmation line is a purchase line's
// first effect; no disbursement cites a purchase line, a payment citing its purchase (§4.12). The evidence
// links' columns are indexed by IX-08, the documents' by their chains.
return new class extends Migration
{
    /** @var array<string, array{string, list<string>, string, list<string>}> */
    private const KEYS = [
        'documents_c32_dropship_confirmation_foreign' => ['documents', ['company_id', 'dropship_confirmation_id'], 'dropship_confirmations', ['company_id', 'id']],
        'documents_c32_invoice_foreign' => ['documents', ['company_id', 'invoice_id'], 'invoices', ['company_id', 'id']],
        'documents_c32_payment_foreign' => ['documents', ['company_id', 'payment_id'], 'payments', ['company_id', 'id']],
        'documents_c32_delivery_foreign' => ['documents', ['company_id', 'delivery_id'], 'deliveries', ['company_id', 'id']],
        'documents_c32_service_handover_foreign' => ['documents', ['company_id', 'service_handover_id'], 'service_handovers', ['company_id', 'id']],
        'document_versions_c32_invoice_version_foreign' => ['document_versions', ['company_id', 'invoice_version_id'], 'invoice_versions', ['company_id', 'id']],
        'evidence_links_c32_delivery_foreign' => ['evidence_links', ['company_id', 'delivery_id'], 'deliveries', ['company_id', 'id']],
        'evidence_links_c32_dropship_confirmation_foreign' => ['evidence_links', ['company_id', 'dropship_confirmation_id'], 'dropship_confirmations', ['company_id', 'id']],
        'evidence_links_c32_service_handover_foreign' => ['evidence_links', ['company_id', 'service_handover_id'], 'service_handovers', ['company_id', 'id']],
        'evidence_links_c32_payment_foreign' => ['evidence_links', ['company_id', 'payment_id'], 'payments', ['company_id', 'id']],
        'evidence_links_c32_other_cash_receipt_foreign' => ['evidence_links', ['company_id', 'other_cash_receipt_id'], 'other_cash_receipts', ['company_id', 'id']],
        'evidence_links_c32_disbursement_foreign' => ['evidence_links', ['company_id', 'disbursement_id'], 'disbursements', ['company_id', 'id']],
        'evidence_links_c32_deduction_settlement_foreign' => ['evidence_links', ['company_id', 'deduction_settlement_id'], 'deduction_settlements', ['company_id', 'id']],
        'evidence_links_c32_receivable_writeoff_foreign' => ['evidence_links', ['company_id', 'receivable_writeoff_id'], 'receivable_writeoffs', ['company_id', 'id']],
        'cost_attributions_dropship_line_foreign' => ['cost_attributions', ['dropship_confirmation_line_id'], 'dropship_confirmation_lines', ['id']],
        'expenses_c32_deduction_settlement_foreign' => ['expenses', ['company_id', 'deduction_settlement_id'], 'deduction_settlements', ['company_id', 'id']],
        'stock_consumption_balances_dropship_line_foreign' => ['stock_consumption_balances', ['dropship_confirmation_line_id'], 'dropship_confirmation_lines', ['id']],
        'stock_restorations_l31_dropship_unit_foreign' => ['stock_restorations', ['dropship_confirmation_line_id', 'cited_unit_id', 'cited_ratio_to_base'], 'dropship_confirmation_lines', ['id', 'entered_unit_id', 'ratio_to_base']],
        'stock_restorations_c08_dropship_source_foreign' => ['stock_restorations', ['dropship_confirmation_line_id', 'source_company_id'], 'dropship_confirmation_lines', ['id', 'company_id']],
        'stock_restorations_dropship_product_foreign' => ['stock_restorations', ['dropship_confirmation_line_id', 'product_id'], 'dropship_confirmation_lines', ['id', 'product_id']],
    ];

    private const EFFECT = <<<'SQL'
        create or replace function purchase_line_has_effect(line bigint) returns boolean
        language plpgsql stable as $$
        begin
            return exists (select from receiving_lines where purchase_line_id = line)
                %s
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
        SQL;

    public function up(): void
    {
        foreach (self::KEYS as $name => [$table, $columns, $references, $referenced]) {
            TableRules::for($table)->foreign($name, $columns, $references, $referenced);
        }

        // A version's rendered invoice version is looked up by the restricted delete of a DRAFT version (§20).
        TableRules::for('document_versions')->index('document_versions_invoice_version', 'invoice_version_id', 'invoice_version_id is not null');

        DB::unprepared(sprintf(self::EFFECT, 'or exists (select from dropship_confirmation_lines where purchase_line_id = line)'));

        DB::unprepared(<<<'SQL'
            create or replace function cost_attributions_dropship_line_within_bearer_company() returns trigger
            language plpgsql as $$
            begin
                if exists (select from dropship_confirmation_lines l where l.id = new.dropship_confirmation_line_id and l.company_id <> new.bearer_company_id) then
                    raise exception 'A drop-ship line''s direct cost is borne in its company, a return settlement aside (DATABASE §4.8, §15; DIR-090).'
                        using errcode = 'check_violation', constraint = 'cost_attributions_dropship_line_within_bearer_company';
                end if;

                return null;
            end
            $$;

            create constraint trigger cost_attributions_dropship_line_within_bearer_company
                after insert on cost_attributions
                for each row when (new.dropship_confirmation_line_id is not null and new.cause <> 'RETURN_SETTLEMENT')
                execute function cost_attributions_dropship_line_within_bearer_company();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger cost_attributions_dropship_line_within_bearer_company on cost_attributions; drop function cost_attributions_dropship_line_within_bearer_company()');
        DB::unprepared(sprintf(self::EFFECT, ''));
        DB::statement('drop index document_versions_invoice_version');

        foreach (array_reverse(self::KEYS) as $name => [$table]) {
            DB::statement("alter table {$table} drop constraint {$name}");
        }
    }
};
