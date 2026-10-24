<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.12 `payment_applications` (SR, the supersedable layer) and §11.3: money of a source — a payment or
// an opening credit — applied to an invoice or backing a refund, both arcs of one company and one client by
// composite keys (BR-FIN-03/07; FS-10; C-14). Its amount never changes; it leaves ACTIVE once, its end columns
// set together through a column grant and the trigger, by the exits of its target: an invoice's REALLOCATED,
// INVOICE_CORRECTED, SOURCE_CONTRA or RE_RECORDED, a refund's only RE_RECORDED, SOURCE_CONTRA or REFUND_CONTRA,
// a contra exit naming its contra (L-46; C-44). A reallocation may split one application into several; a
// refund application may resolve the shortfall another left on the same refund.
return new class extends Migration
{
    private const END = ['state', 'end_reason', 'end_contra_id', 'end_command_id'];

    public function up(): void
    {
        Schema::create('payment_applications', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('company_id');
            $table->bigInteger('client_organization_id');
            $table->bigInteger('payment_id')->nullable();
            $table->bigInteger('opening_customer_credit_id')->nullable();
            $table->bigInteger('invoice_id')->nullable();
            $table->bigInteger('refund_disbursement_id')->nullable();
            $table->text('target_kind');
            Columns::money($table, 'amount');
            $table->text('state')->default('ACTIVE');
            $table->text('end_reason')->nullable();
            $table->bigInteger('end_contra_id')->nullable();
            $table->uuid('end_command_id')->nullable();
            $table->bigInteger('supersedes_application_id')->nullable();
            $table->bigInteger('resolves_application_id')->nullable();
            Columns::businessDate($table);
            $table->text('reason')->nullable();
            Columns::commandIdentity($table, 'payment_applications');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            // IX-06: a source's applications over every state.
            $table->index('payment_id', 'payment_applications_ix06_payment');
            $table->index('opening_customer_credit_id', 'payment_applications_ix06_credit');
            $table->index('supersedes_application_id');
            $table->index('end_contra_id');
        });

        TableRules::for('payment_applications')
            ->unique('payment_applications_c32_company_key', ['company_id', 'id'])
            ->unique('payment_applications_c14_client_key', ['company_id', 'client_organization_id', 'id'])
            // The key a resolving application or a refund's contra cites an application of the same refund through.
            ->unique('payment_applications_c44_refund_key', ['id', 'refund_disbursement_id'])
            ->foreign('payment_applications_c14_payment_foreign', ['company_id', 'client_organization_id', 'payment_id'], 'payments', ['company_id', 'client_organization_id', 'id'])
            ->foreign('payment_applications_c14_credit_foreign', ['company_id', 'client_organization_id', 'opening_customer_credit_id'], 'opening_customer_credits', ['company_id', 'client_organization_id', 'id'])
            ->foreign('payment_applications_c14_invoice_foreign', ['company_id', 'client_organization_id', 'invoice_id'], 'invoices', ['company_id', 'client_organization_id', 'id'])
            ->foreign('payment_applications_c14_refund_foreign', ['company_id', 'client_organization_id', 'refund_disbursement_id'], 'disbursements', ['company_id', 'client_organization_id', 'id'])
            ->foreign('payment_applications_c14_supersedes_foreign', ['company_id', 'client_organization_id', 'supersedes_application_id'], 'payment_applications', ['company_id', 'client_organization_id', 'id'])
            ->foreign('payment_applications_c44_resolves_foreign', ['resolves_application_id', 'refund_disbursement_id'], 'payment_applications', ['id', 'refund_disbursement_id'])
            ->codes('payment_applications_target_kind_code', 'target_kind', ['INVOICE', 'REFUND'])
            ->codes('payment_applications_state_code', 'state', ['ACTIVE', 'SUPERSEDED', 'RELEASED'])
            ->codes('payment_applications_end_reason_code', 'end_reason', ['REALLOCATED', 'INVOICE_CORRECTED', 'SOURCE_CONTRA', 'RE_RECORDED', 'REFUND_CONTRA'], nullable: true)
            ->check('payment_applications_one_source', 'num_nonnulls(payment_id, opening_customer_credit_id) = 1')
            ->check('payment_applications_target_by_kind', "num_nonnulls(invoice_id, refund_disbursement_id) = 1 and (target_kind = 'INVOICE') = (invoice_id is not null)")
            ->check('payment_applications_amount_positive', 'amount > 0')
            ->check('payment_applications_end_columns', "(state = 'ACTIVE') = (end_reason is null) and num_nonnulls(end_reason, end_command_id) in (0, 2)")
            ->check('payment_applications_c44_end_contra', "coalesce(end_reason in ('SOURCE_CONTRA', 'REFUND_CONTRA'), false) = (end_contra_id is not null)")
            ->check('payment_applications_c44_exit_by_target', "end_reason is null or (target_kind = 'REFUND' and end_reason in ('RE_RECORDED', 'SOURCE_CONTRA', 'REFUND_CONTRA')) or (target_kind = 'INVOICE' and end_reason in ('REALLOCATED', 'INVOICE_CORRECTED', 'SOURCE_CONTRA', 'RE_RECORDED'))")
            ->check('payment_applications_c44_resolves_refund', "resolves_application_id is null or target_kind = 'REFUND'")
            // §20: an invoice's active applications; the lineage of shortfall resolutions (QS-07).
            ->index('payment_applications_active_invoice', 'invoice_id', "state = 'ACTIVE'")
            ->index('payment_applications_resolves', 'resolves_application_id', 'resolves_application_id is not null')
            ->index('payment_applications_c44_source_contra_refund', 'refund_disbursement_id', "end_reason = 'SOURCE_CONTRA'")
            ->index('payment_applications_refund', 'refund_disbursement_id', 'refund_disbursement_id is not null')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(self::END);

        DB::unprepared(<<<'SQL'
            create or replace function payment_applications_c44_ended_once() returns trigger
            language plpgsql as $$
            declare
                ending text[] := array['state', 'end_reason', 'end_contra_id', 'end_command_id'];
            begin
                if old.state <> 'ACTIVE' or new.state = 'ACTIVE'
                    or (to_jsonb(new) - ending) is distinct from (to_jsonb(old) - ending)
                    or (new.end_reason = 'SOURCE_CONTRA' and new.end_contra_id is not null and not exists (
                        select from financial_contras c where c.id = new.end_contra_id
                            and (c.payment_id = new.payment_id or c.opening_customer_credit_id = new.opening_customer_credit_id)
                    ))
                    or (new.end_reason = 'REFUND_CONTRA' and new.end_contra_id is not null and not exists (
                        select from financial_contras c where c.id = new.end_contra_id and c.disbursement_id = new.refund_disbursement_id
                    )) then
                    raise exception 'An application leaves ACTIVE once, by its end columns only (DATABASE §4.12, §11.3; L-46; C-44).'
                        using errcode = 'check_violation', constraint = 'payment_applications_c44_ended_once';
                end if;

                return new;
            end
            $$;

            create trigger payment_applications_c44_ended_once
                before update on payment_applications
                for each row execute function payment_applications_c44_ended_once();

            create or replace function payment_applications_c44_superseded_by_successor() returns trigger
            language plpgsql as $$
            begin
                if not exists (
                    select from payment_applications s
                    where s.supersedes_application_id = new.id
                        and s.refund_disbursement_id is not distinct from new.refund_disbursement_id
                        and (new.end_reason = 'REALLOCATED'
                            or exists (select from payments p where p.id = s.payment_id and p.rerecords_payment_id = new.payment_id))
                ) then
                    raise exception 'A reallocation or re-recording commits with the application that supersedes it, a re-recording''s from the payment that re-records its source (DATABASE §4.12, §11.3; CM-19, CM-21; C-44).'
                        using errcode = 'check_violation', constraint = 'payment_applications_c44_superseded_by_successor';
                end if;

                return null;
            end
            $$;

            create constraint trigger payment_applications_c44_superseded_by_successor
                after update on payment_applications
                deferrable initially deferred
                for each row when (new.end_reason in ('REALLOCATED', 'RE_RECORDED'))
                execute function payment_applications_c44_superseded_by_successor();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('payment_applications');
        DB::unprepared('drop function if exists payment_applications_c44_ended_once(); drop function if exists payment_applications_c44_superseded_by_successor()');
    }
};
