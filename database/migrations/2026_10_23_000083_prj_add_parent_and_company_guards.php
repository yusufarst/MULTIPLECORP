<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// DIR-090 (2)–(4) and APPR-019 (DATABASE §4.5, §4.6, §4.8, §19): the guards that hold TASK-007's references
// within their parent and a direct cost attribution within the purchase's company, no column added. A
// share-schedule row's share is a share of its own charge and version, by a composite key; a pin's and a
// quotation line's project line, a charge share's line and a fallback expense's line are of their parent's
// project or purchase, and a direct attribution's purchase records are the bearer company's but on a
// RETURN_SETTLEMENT row, borne by its case's causal project (§15) — each by a trigger after the keys, at the
// insert, the only write of those columns. A DRAFT project's deletion takes its SIPLAH details (§19; L-37).
return new class extends Migration
{
    private const SHARE_ROWS = ['lot_cost_entries', 'cost_attributions'];

    /** @var array<string, array{string, string, string}> trigger => [table, condition, refusal] */
    private const TRIGGERS = [
        'confirmation_line_pins_line_of_confirmation_project' => ['confirmation_line_pins', 'true', <<<'SQL'
            not exists (select from project_lines l join commercial_confirmations c on c.project_id = l.project_id
                where l.id = new.project_line_id and c.id = new.commercial_confirmation_id)
            SQL],
        'quotation_revision_lines_line_of_revision_project' => ['quotation_revision_lines', 'true', <<<'SQL'
            not exists (select from project_lines l join quotation_revisions r on r.project_id = l.project_id
                where l.id = new.project_line_id and r.id = new.quotation_revision_id)
            SQL],
        'purchase_charge_line_shares_line_of_charge_purchase' => ['purchase_charge_line_shares', 'true', <<<'SQL'
            not exists (select from purchase_lines l join purchase_charges c on c.purchase_id = l.purchase_id
                where l.id = new.purchase_line_id and c.id = new.purchase_charge_id)
            SQL],
        'expenses_fallback_line_of_charge_purchase' => ['expenses', 'new.purchase_line_id is not null and new.purchase_charge_id is not null', <<<'SQL'
            not exists (select from purchase_lines l join purchase_charges c on c.purchase_id = l.purchase_id
                where l.id = new.purchase_line_id and c.id = new.purchase_charge_id)
            SQL],
        'cost_attributions_direct_within_bearer_company' => ['cost_attributions', "new.stock_consumption_id is null and new.cause <> 'RETURN_SETTLEMENT'", <<<'SQL'
            exists (select from purchase_lines l where l.id in (new.purchase_line_id, new.respread_from_purchase_line_id) and l.company_id <> new.bearer_company_id)
                or exists (select from purchase_charges c where c.id = new.purchase_charge_id and c.company_id <> new.bearer_company_id)
                or exists (select from purchase_charge_line_shares s where s.id = new.purchase_charge_line_share_id and s.company_id <> new.bearer_company_id)
            SQL],
    ];

    public function up(): void
    {
        TableRules::for('purchase_charge_line_shares')
            ->unique('purchase_charge_line_shares_charge_version_key', ['id', 'purchase_charge_id', 'allocation_version']);

        foreach (self::SHARE_ROWS as $table) {
            TableRules::for($table)->foreign("{$table}_share_of_charge_version_foreign",
                ['purchase_charge_line_share_id', 'purchase_charge_id', 'allocation_version'],
                'purchase_charge_line_shares', ['id', 'purchase_charge_id', 'allocation_version']);
        }

        DB::statement('alter table project_siplah_details drop constraint project_siplah_details_project_id_foreign');
        TableRules::for('project_siplah_details')
            ->foreign('project_siplah_details_project_id_foreign', ['project_id'], 'projects', ['id'], cascadeDraftDelete: true);

        foreach (self::TRIGGERS as $name => [$table, $condition, $refusal]) {
            DB::unprepared(<<<SQL
                create or replace function {$name}() returns trigger
                language plpgsql as \$\$
                begin
                    if {$refusal} then
                        raise exception 'A reference is held within its parent, a direct cost within the purchase''s company (DATABASE §4.5–§4.8; DIR-090).'
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
    }

    public function down(): void
    {
        foreach (self::TRIGGERS as $name => [$table]) {
            DB::unprepared("drop trigger {$name} on {$table}; drop function {$name}()");
        }

        DB::statement('alter table project_siplah_details drop constraint project_siplah_details_project_id_foreign');
        TableRules::for('project_siplah_details')->foreign('project_siplah_details_project_id_foreign', ['project_id'], 'projects', ['id']);

        foreach (self::SHARE_ROWS as $table) {
            DB::statement("alter table {$table} drop constraint {$table}_share_of_charge_version_foreign");
        }

        DB::statement('alter table purchase_charge_line_shares drop constraint purchase_charge_line_shares_charge_version_key');
    }
};
