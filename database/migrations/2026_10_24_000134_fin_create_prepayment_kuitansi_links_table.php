<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `prepayment_kuitansi_links` (SR, state set once) and §11.4: the link of a pre-payment Kuitansi
// — a DOC-05 document of mode PRE_PAYMENT — to the payment that settled it on an invoice, of one company
// (C-32), never proof of receipt (AX-31; DIR-024 D-4). One LINKED row per payment and invoice (C-43); a
// correction that lowers the linked total unlinks it once, with reason, through a column grant and the
// trigger.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepayment_kuitansi_links', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->bigInteger('document_id');
            $table->bigInteger('invoice_id');
            $table->bigInteger('payment_id');
            $table->text('state')->default('LINKED');
            $table->text('unlink_reason')->nullable();
            Columns::commandIdentity($table, 'prepayment_kuitansi_links');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('document_id');
            $table->index('payment_id');
        });

        TableRules::for('prepayment_kuitansi_links')
            ->foreign('prepayment_kuitansi_links_c32_document_foreign', ['company_id', 'document_id'], 'documents', ['company_id', 'id'])
            ->foreign('prepayment_kuitansi_links_c32_invoice_foreign', ['company_id', 'invoice_id'], 'invoices', ['company_id', 'id'])
            ->foreign('prepayment_kuitansi_links_c32_payment_foreign', ['company_id', 'payment_id'], 'payments', ['company_id', 'id'])
            ->codes('prepayment_kuitansi_links_state_code', 'state', ['LINKED', 'UNLINKED'])
            ->check('prepayment_kuitansi_links_unlink_reason', "(state = 'UNLINKED') = (unlink_reason is not null)")
            ->uniqueIndex('prepayment_kuitansi_links_c43_one_link', 'payment_id, invoice_id', "state = 'LINKED'")
            // §20 and QS-19: an invoice's open links.
            ->index('prepayment_kuitansi_links_qs19_linked', 'invoice_id', "state = 'LINKED'")
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['state', 'unlink_reason']);

        DB::unprepared(<<<'SQL'
            create or replace function prepayment_kuitansi_links_c43_pre_payment_document() returns trigger
            language plpgsql as $$
            begin
                if not exists (select from documents d where d.id = new.document_id and d.document_type_code = 'DOC-05' and d.mode = 'PRE_PAYMENT') then
                    raise exception 'A Kuitansi link cites a pre-payment DOC-05 (DATABASE §4.12; AX-31).'
                        using errcode = 'check_violation', constraint = 'prepayment_kuitansi_links_c43_pre_payment_document';
                end if;

                return new;
            end
            $$;

            create trigger prepayment_kuitansi_links_c43_pre_payment_document
                before insert on prepayment_kuitansi_links
                for each row execute function prepayment_kuitansi_links_c43_pre_payment_document();

            create or replace function prepayment_kuitansi_links_c43_unlinked_once() returns trigger
            language plpgsql as $$
            begin
                if old.state <> 'LINKED' or (to_jsonb(new) - array['state', 'unlink_reason']) is distinct from (to_jsonb(old) - array['state', 'unlink_reason']) then
                    raise exception 'A Kuitansi link changes only by its one unlinking (DATABASE §4.12; AX-31).'
                        using errcode = 'check_violation', constraint = 'prepayment_kuitansi_links_c43_unlinked_once';
                end if;

                return new;
            end
            $$;

            create trigger prepayment_kuitansi_links_c43_unlinked_once
                before update on prepayment_kuitansi_links
                for each row execute function prepayment_kuitansi_links_c43_unlinked_once();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('prepayment_kuitansi_links');
        DB::unprepared('drop function if exists prepayment_kuitansi_links_c43_pre_payment_document(); drop function if exists prepayment_kuitansi_links_c43_unlinked_once()');
    }
};
