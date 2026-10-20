<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.2 `company_bank_accounts` (MD, immutable detail): a company's own accounts, which payments,
// disbursements, statement lines and issued document versions reference through the company-scoped key,
// so a Company B record can only name a Company B account (§15; C-32). Once any fact references a row,
// its detail columns never change — a change is a new row plus deactivation — so every record keeps the
// destination actually used (BR-SN-02; §14). The trigger finds the references in the catalogue, so the
// keys later tables add to this table are covered as they are created.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_bank_accounts', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->text('bank_name');
            $table->text('account_number');
            $table->text('account_holder');
            $table->text('branch')->nullable();
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'company_bank_accounts');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('company_bank_accounts')
            ->unique('company_bank_accounts_account_unique', ['company_id', 'bank_name', 'account_number'])
            ->unique('company_bank_accounts_c32_company_key', ['company_id', 'id'])
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate([
                'bank_name', 'account_number', 'account_holder', 'branch',
                'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason',
                'lock_version', 'updated_at',
            ]);

        DB::unprepared(<<<'SQL'
            create or replace function company_bank_accounts_detail_immutable() returns trigger
            language plpgsql as $$
            declare
                reference record;
                referenced boolean;
            begin
                if (new.bank_name, new.account_number, new.account_holder, new.branch)
                    is not distinct from (old.bank_name, old.account_number, old.account_holder, old.branch) then
                    return new;
                end if;

                -- The row lock a detail change holds does not conflict with the key-share lock of a fact
                -- being inserted; this one does, so the check below waits for that fact and sees it.
                perform 1 from company_bank_accounts where id = old.id for update;

                for reference in
                    select c.conrelid::regclass as referencing_table, a.attname as referencing_column
                    from pg_constraint c
                    cross join lateral unnest(c.conkey, c.confkey) as k(referencing_attnum, referenced_attnum)
                    join pg_attribute a on a.attrelid = c.conrelid and a.attnum = k.referencing_attnum
                    join pg_attribute r on r.attrelid = c.confrelid and r.attnum = k.referenced_attnum
                    where c.contype = 'f' and c.confrelid = 'company_bank_accounts'::regclass and r.attname = 'id'
                loop
                    execute format('select exists (select from %s where %I = $1)', reference.referencing_table, reference.referencing_column)
                        into referenced using old.id;

                    if referenced then
                        raise exception 'A bank account''s details never change once a fact references it (DATABASE §4.2; BR-SN-02).'
                            using errcode = 'check_violation', constraint = 'company_bank_accounts_detail_immutable';
                    end if;
                end loop;

                return new;
            end
            $$;

            create trigger company_bank_accounts_detail_immutable
                before update on company_bank_accounts
                for each row execute function company_bank_accounts_detail_immutable();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('company_bank_accounts');
        DB::unprepared('drop function if exists company_bank_accounts_detail_immutable()');
    }
};
