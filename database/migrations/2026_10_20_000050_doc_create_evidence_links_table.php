<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.10 `evidence_links` (SR, unlinked once): typed evidence linkage without morph columns
// (D-DB-10) — one target per link, an exclusive arc. Company evidence links within its company, keyed
// with it (C-32); pool evidence belongs to no company and links only to pool records — a pool-wide
// movement, an unattributed-loss case, an import batch — so evidence on a pending case invents no owner
// (DIR-027; C-58). A link is unlinked once, with reason and actor (§19). Each target column is indexed
// for the cascade derivations (IX-08). The correction case and the import batch are keyed here; the
// other targets are the records of TASK-007–TASK-009, which add their keys (DIR-089 (1)).
return new class extends Migration
{
    private const TARGETS = [
        'project_id', 'commercial_confirmation_id', 'purchase_id', 'stock_movement_id', 'delivery_id',
        'dropship_confirmation_id', 'service_handover_id', 'payment_id', 'other_cash_receipt_id', 'disbursement_id',
        'expense_id', 'deduction_settlement_id', 'receivable_writeoff_id', 'correction_case_id', 'admin_requirement_id',
        'unattributed_loss_case_id', 'import_batch_id',
    ];

    public function up(): void
    {
        Schema::create('evidence_links', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('evidence_document_id');
            $table->text('evidence_scope');
            $table->bigInteger('company_id')->nullable();
            $table->text('role');

            foreach (self::TARGETS as $target) {
                $table->bigInteger($target)->nullable();
            }

            Columns::timestamp($table, 'unlinked_at')->nullable();
            Columns::user($table, 'unlinked_by')->nullable();
            $table->text('unlink_reason')->nullable();
            Columns::commandIdentity($table, 'evidence_links');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('evidence_document_id');
        });

        $rules = TableRules::for('evidence_links')
            ->foreign('evidence_links_c58_scope_foreign', ['evidence_document_id', 'evidence_scope'], 'evidence_documents', ['id', 'evidence_scope'])
            ->foreign('evidence_links_c32_evidence_foreign', ['company_id', 'evidence_document_id'], 'evidence_documents', ['company_id', 'id'])
            ->foreign('evidence_links_c32_correction_case_foreign', ['company_id', 'correction_case_id'], 'correction_cases', ['company_id', 'id'])
            ->foreign('evidence_links_import_batch_foreign', ['import_batch_id'], 'import_batches', ['id'])
            ->codes('evidence_links_scope_code', 'evidence_scope', ['COMPANY', 'POOL'])
            ->codes('evidence_links_role_code', 'role', ['PRIMARY_PROOF', 'SUPPORTING', 'CLIENT_ORIGINAL', 'REVIEWED'])
            ->check('evidence_links_c58_company_when_company_scoped', "(evidence_scope = 'COMPANY') = (company_id is not null)")
            ->check('evidence_links_one_target', 'num_nonnulls('.implode(', ', self::TARGETS).') = 1')
            ->check('evidence_links_c58_pool_targets', "evidence_scope = 'COMPANY' or num_nonnulls(stock_movement_id, unattributed_loss_case_id, import_batch_id) = 1")
            ->check('evidence_links_unlink_columns', 'num_nonnulls(unlinked_at, unlinked_by, unlink_reason) in (0, 3)');

        foreach (self::TARGETS as $target) {
            $rules->index('evidence_links_ix08_'.preg_replace('/_id$/', '', $target), $target, "{$target} is not null");
        }

        $rules->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['unlinked_at', 'unlinked_by', 'unlink_reason']);

        DB::unprepared(<<<'SQL'
            create or replace function evidence_links_unlink_once() returns trigger
            language plpgsql as $$
            begin
                if old.unlinked_at is not null or old.unlinked_by is not null or old.unlink_reason is not null
                    or (to_jsonb(new) - array['unlinked_at', 'unlinked_by', 'unlink_reason'])
                        is distinct from (to_jsonb(old) - array['unlinked_at', 'unlinked_by', 'unlink_reason']) then
                    raise exception 'An evidence link is unlinked once and changes no other column (DATABASE §4.10, §19).'
                        using errcode = 'check_violation', constraint = 'evidence_links_unlink_once';
                end if;

                return new;
            end
            $$;

            create trigger evidence_links_unlink_once
                before update on evidence_links
                for each row execute function evidence_links_unlink_once();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('evidence_links');
        DB::unprepared('drop function if exists evidence_links_unlink_once()');
    }
};
