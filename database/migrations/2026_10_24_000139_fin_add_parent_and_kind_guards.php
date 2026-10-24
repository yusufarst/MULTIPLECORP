<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// DIR-092 and APPR-021, expand-only over existing columns: each FUL and FIN row held within its parent beyond
// its company (DIR-092 (1)) — a drop-ship line's purchase line and demand line within its confirmation's
// purchase and project; a delivery line's and a closure's shipment of their own demand line, by composite
// keys; a delivery line's, a handover line's and an invoice line's demand line within its delivery's,
// handover's or invoice's project —; a money-fact guard's kind held to its disbursement's kind (DIR-092 (3)).
return new class extends Migration
{
    /** @var array<string, array{string, list<string>}> the shipment keys the composite keys reference */
    private const PARENT_KEYS = [
        'dispatch_lines_project_line_key' => ['dispatch_lines', ['id', 'project_line_id']],
        'dropship_confirmation_lines_project_line_key' => ['dropship_confirmation_lines', ['id', 'project_line_id']],
    ];

    /** @var array<string, array{string, list<string>, string, list<string>}> */
    private const KEYS = [
        'delivery_lines_dispatch_of_line_foreign' => ['delivery_lines', ['dispatch_line_id', 'project_line_id'], 'dispatch_lines', ['id', 'project_line_id']],
        'delivery_lines_dropship_of_line_foreign' => ['delivery_lines', ['dropship_confirmation_line_id', 'project_line_id'], 'dropship_confirmation_lines', ['id', 'project_line_id']],
        'delivery_closures_dispatch_of_line_foreign' => ['delivery_closures', ['dispatch_line_id', 'project_line_id'], 'dispatch_lines', ['id', 'project_line_id']],
        'delivery_closures_dropship_of_line_foreign' => ['delivery_closures', ['dropship_confirmation_line_id', 'project_line_id'], 'dropship_confirmation_lines', ['id', 'project_line_id']],
    ];

    /** @var array<string, array{string, string, string}> trigger => [table, condition, refusal] */
    private const TRIGGERS = [
        'dropship_confirmation_lines_within_confirmation' => ['dropship_confirmation_lines', 'true', <<<'SQL'
            not exists (
                select from dropship_confirmations c
                    join purchase_lines pl on pl.id = new.purchase_line_id and pl.purchase_id = c.purchase_id
                    join project_lines l on l.id = new.project_line_id and l.project_id = c.project_id
                where c.id = new.dropship_confirmation_id
            )
            SQL],
        'delivery_lines_within_project' => ['delivery_lines', 'true', <<<'SQL'
            not exists (select from deliveries d join project_lines l on l.project_id = d.project_id where d.id = new.delivery_id and l.id = new.project_line_id)
            SQL],
        'service_handover_lines_within_project' => ['service_handover_lines', 'true', <<<'SQL'
            not exists (select from service_handovers h join project_lines l on l.project_id = h.project_id where h.id = new.service_handover_id and l.id = new.project_line_id)
            SQL],
        'invoice_version_lines_within_project' => ['invoice_version_lines', 'new.project_line_id is not null', <<<'SQL'
            not exists (
                select from invoice_versions v join invoices i on i.id = v.invoice_id join project_lines l on l.project_id = i.project_id
                where v.id = new.invoice_version_id and l.id = new.project_line_id
            )
            SQL],
        'money_fact_balances_kind_of_disbursement' => ['money_fact_balances', 'new.disbursement_id is not null', <<<'SQL'
            not exists (
                select from disbursements d where d.id = new.disbursement_id and case new.fact_kind
                    when 'REFUND' then d.kind = 'REFUND'
                    when 'TRANSFER_OUT' then d.kind = 'INTERCOMPANY_TRANSFER_OUT'
                    else d.kind not in ('REFUND', 'INTERCOMPANY_TRANSFER_OUT')
                end
            )
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

        foreach (self::TRIGGERS as $name => [$table, $condition, $refusal]) {
            DB::unprepared(<<<SQL
                create or replace function {$name}() returns trigger
                language plpgsql as \$\$
                begin
                    if {$refusal} then
                        raise exception 'A row is held within its parent, a money fact to its disbursement''s kind (DATABASE §4.9, §4.12; DIR-092).'
                            using errcode = 'check_violation', constraint = '{$name}';
                    end if;

                    return null;
                end
                \$\$;

                create constraint trigger {$name}
                    after insert or update on {$table}
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
