<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C-50 (DATABASE §18; L-09; BR-DT-04, BR-DT-06): no committed fact carries a business date later than today
// in WIB. A CHECK cannot read the current date safely, so one function guards every table whose rows carry a
// business date, before each insert and each change of the date; the command refuses it first (SF-DATE). An
// earlier real date is legitimate for the Owner and an Admin alike (BR-DT-02). Expand-only.
return new class extends Migration
{
    /** The tables of TASK-005–TASK-009 whose rows carry `business_date`. */
    private const TABLES = [
        'bank_statement_lines', 'commercial_confirmations', 'cost_attributions', 'deduction_settlements', 'deliveries',
        'delivery_closures', 'disbursements', 'document_versions', 'dropship_confirmations', 'expenses',
        'financial_contras', 'inventory_lots', 'invoice_versions', 'line_scope_cancellations', 'lot_cost_entries',
        'opening_customer_credits', 'other_cash_receipts', 'payment_applications', 'payments', 'purchase_line_closures',
        'purchases', 'receivable_writeoffs', 'service_handovers', 'stock_movements', 'unattributed_loss_cases',
        'unattributed_loss_resolutions',
    ];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function c50_business_date_not_future() returns trigger
            language plpgsql as $$
            begin
                if new.business_date > (now() at time zone 'Asia/Jakarta')::date then
                    raise exception 'A committed business date is never later than today in WIB (C-50; L-09).'
                        using errcode = 'check_violation', constraint = 'c50_business_date_not_future';
                end if;

                return new;
            end
            $$;
            SQL);

        foreach (self::TABLES as $table) {
            DB::statement("create trigger c50_business_date_not_future before insert or update of business_date on {$table} for each row execute function c50_business_date_not_future()");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("drop trigger c50_business_date_not_future on {$table}");
        }

        DB::statement('drop function c50_business_date_not_future()');
    }
};
