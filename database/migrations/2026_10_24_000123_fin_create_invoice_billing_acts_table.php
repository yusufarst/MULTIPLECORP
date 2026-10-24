<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `invoice_billing_acts` (AD) and §11.2: the billing of an invoice, once — one root act and a
// linear correction chain (AX-17; CM-25; C-41). An opening receivable carries an OPENING act with its legacy
// dates and import row, without a due date only when aged MIGRASI (BR-FIN-14). A replacement inherits the
// replaced invoice's current act: the inherited act cites only the invoice its own replaces, copies its
// dates exactly, and a deferred trigger refuses at commit a replacement of a billed invoice whose root act is
// missing, fresh or cites a superseded act — so aging never resets (L-30; C-17).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_billing_acts', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'invoice_id', 'invoices');
            $table->date('billed_at');
            $table->date('due_date')->nullable();
            $table->text('kind');
            $table->bigInteger('inherited_from_act_id')->nullable();
            $table->bigInteger('inherited_from_invoice_id')->nullable();
            Columns::reference($table, 'import_row_id', 'import_rows')->nullable()->unique();
            $table->bigInteger('supersedes_act_id')->nullable()->unique();
            $table->text('reason')->nullable();
            Columns::user($table, 'decided_by');
            Columns::commandIdentity($table, 'invoice_billing_acts');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
        });

        TableRules::for('invoice_billing_acts')
            ->unique('invoice_billing_acts_chain_key', ['invoice_id', 'id'])
            // The key an inherited act copies its cited act's dates through (L-30; C-17).
            ->unique('invoice_billing_acts_c17_dates_key', ['id', 'invoice_id', 'billed_at', 'due_date'])
            ->foreign('invoice_billing_acts_c17_replaced_invoice_foreign', ['invoice_id', 'inherited_from_invoice_id'], 'invoices', ['id', 'replaces_invoice_id'])
            ->foreign('invoice_billing_acts_c17_inherited_foreign', ['inherited_from_act_id', 'inherited_from_invoice_id', 'billed_at', 'due_date'], 'invoice_billing_acts', ['id', 'invoice_id', 'billed_at', 'due_date'])
            ->foreign('invoice_billing_acts_c41_supersedes_foreign', ['invoice_id', 'supersedes_act_id'], 'invoice_billing_acts', ['invoice_id', 'id'])
            ->codes('invoice_billing_acts_kind_code', 'kind', ['BILLING', 'CORRECTION', 'INHERITED', 'OPENING'])
            ->check('invoice_billing_acts_c17_inherited', "(kind = 'INHERITED') = (inherited_from_act_id is not null) and num_nonnulls(inherited_from_act_id, inherited_from_invoice_id) in (0, 2)")
            ->check('invoice_billing_acts_c41_correction_supersedes', "(kind = 'CORRECTION') = (supersedes_act_id is not null)")
            ->check('invoice_billing_acts_c47_opening_import_row', "(kind = 'OPENING') = (import_row_id is not null)")
            ->check('invoice_billing_acts_due_date', "due_date is not null or kind = 'OPENING'")
            ->uniqueIndex('invoice_billing_acts_c41_one_root', 'invoice_id', 'supersedes_act_id is null')
            ->commandIdentity()
            ->grant('select, insert');

        DB::unprepared(<<<'SQL'
            create or replace function invoice_billing_acts_c41_dates() returns trigger
            language plpgsql as $$
            begin
                if (new.due_date is null and not exists (select from invoices i where i.id = new.invoice_id and i.aging_basis = 'MIGRASI'))
                    -- The composite key skips a NULL due date, so an inherited act's dates are compared here too.
                    or (new.inherited_from_act_id is not null and not exists (
                        select from invoice_billing_acts a where a.id = new.inherited_from_act_id
                            and a.billed_at = new.billed_at and a.due_date is not distinct from new.due_date
                    )) then
                    raise exception 'An act has no due date only for an opening receivable aged MIGRASI, and an inherited act copies its dates (DATABASE §4.12; BR-FIN-14; L-30).'
                        using errcode = 'check_violation', constraint = 'invoice_billing_acts_c41_dates';
                end if;

                return new;
            end
            $$;

            create trigger invoice_billing_acts_c41_dates
                before insert on invoice_billing_acts
                for each row execute function invoice_billing_acts_c41_dates();

            -- A void is final (L-26): an unbilled void stays unbilled, a replaced invoice's current act stays the one its
            -- replacement inherited.
            create or replace function invoice_billing_acts_c41_not_on_void() returns trigger
            language plpgsql as $$
            begin
                if exists (select from invoices i where i.id = new.invoice_id and i.state = 'VOIDED') then
                    raise exception 'A voided invoice takes no billing act (DATABASE §4.12; L-26, L-30; C-41).'
                        using errcode = 'check_violation', constraint = 'invoice_billing_acts_c41_not_on_void';
                end if;

                return new;
            end
            $$;

            create trigger invoice_billing_acts_c41_not_on_void
                before insert on invoice_billing_acts
                for each row execute function invoice_billing_acts_c41_not_on_void();

            create or replace function invoice_billing_acts_c17_inherits_current_act() returns trigger
            language plpgsql as $$
            declare
                replacement invoices%rowtype;
                current_act bigint;
            begin
                -- PL/pgSQL compiles the function once per table, so each branch reads only its own table's row.
                if tg_table_name = 'invoices' then
                    select * into replacement from invoices where id = new.id;
                else
                    select * into replacement from invoices where id = new.invoice_id;
                end if;

                if replacement.replaces_invoice_id is null then
                    return null;
                end if;

                select a.id into current_act from invoice_billing_acts a
                where a.invoice_id = replacement.replaces_invoice_id
                    and not exists (select from invoice_billing_acts b where b.supersedes_act_id = a.id);

                if current_act is not null and not exists (
                    select from invoice_billing_acts r
                    where r.invoice_id = replacement.id and r.supersedes_act_id is null and r.kind = 'INHERITED' and r.inherited_from_act_id = current_act
                ) then
                    raise exception 'A replacement of a billed invoice inherits its current billing act as its root act (DATABASE §4.12, §11.2; L-30; C-17).'
                        using errcode = 'check_violation', constraint = 'invoice_billing_acts_c17_inherits_current_act';
                end if;

                return null;
            end
            $$;

            create constraint trigger invoice_billing_acts_c17_inherits_current_act
                after insert on invoice_billing_acts
                deferrable initially deferred
                for each row execute function invoice_billing_acts_c17_inherits_current_act();

            create constraint trigger invoices_c17_inherits_current_act
                after insert on invoices
                deferrable initially deferred
                for each row when (new.replaces_invoice_id is not null)
                execute function invoice_billing_acts_c17_inherits_current_act();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger invoices_c17_inherits_current_act on invoices');
        Schema::drop('invoice_billing_acts');
        DB::unprepared('drop function if exists invoice_billing_acts_c41_dates(); drop function if exists invoice_billing_acts_c41_not_on_void(); drop function if exists invoice_billing_acts_c17_inherits_current_act()');
    }
};
