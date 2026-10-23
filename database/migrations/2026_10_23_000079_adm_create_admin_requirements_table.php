<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.11 `admin_requirements` (SR): a project's administrative checklist item (BR-ADM-01) — a
// catalogue document, an external document of an evidence type, or free text —, tightened by an Admin,
// relaxed or removed by the Owner only (DIR-024 D-5), never deleted: a removal keeps its actor, time and
// reason (§19). A client original is satisfied only by an upload (FS-12). Satisfaction is derived from its
// links, never stored (WF-ADM-01); waiver eligibility is the Owner's (BR-ADM-02).
return new class extends Migration
{
    private const EVIDENCE_TYPES = [
        'CLIENT_SPK', 'CLIENT_ORDER', 'SIPLAH_ORDER', 'THIRD_PARTY_HPS', 'TAX_INVOICE', 'NPWP', 'NIB', 'BANK_STATEMENT',
        'TAX_WITHHOLDING_PROOF', 'TAX_COLLECTION_PROOF', 'TAX_PAYMENT_PROOF', 'FEE_ADVICE', 'SUPPLIER_BILL', 'DELIVERY_PROOF',
        'PAYMENT_PROOF', 'TRANSFER_PROOF', 'WAREHOUSE_RECORD', 'OPENING_SIGNOFF', 'LEGACY_DOCUMENT', 'OTHER',
    ];

    public function up(): void
    {
        Schema::create('admin_requirements', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('project_id');
            $table->bigInteger('company_id');
            $table->text('requirement_kind');
            $table->text('document_type_code')->nullable();
            $table->text('evidence_type')->nullable();
            $table->text('description');
            $table->boolean('is_required')->default(true);
            $table->text('satisfaction_mode');
            $table->boolean('requires_client_original')->default(false);
            $table->boolean('waiver_eligible')->default(false);
            $table->boolean('is_default_invoice_item')->default(false);
            $table->text('state')->default('ACTIVE');
            Columns::user($table, 'removed_by')->nullable();
            Columns::timestamp($table, 'removed_at')->nullable();
            $table->text('removed_reason')->nullable();
            Columns::commandIdentity($table, 'admin_requirements');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
            $table->index('project_id');
            $table->foreign('document_type_code')->references('code')->on('document_types')->restrictOnDelete();
        });

        TableRules::for('admin_requirements')
            ->unique('admin_requirements_c32_company_key', ['company_id', 'id'])
            ->foreign('admin_requirements_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'])
            ->codes('admin_requirements_kind_code', 'requirement_kind', ['CATALOG_DOCUMENT', 'EXTERNAL_DOCUMENT', 'FREE_TEXT'])
            ->codes('admin_requirements_evidence_type_code', 'evidence_type', self::EVIDENCE_TYPES, nullable: true)
            ->codes('admin_requirements_satisfaction_mode_code', 'satisfaction_mode', ['GENERATED', 'UPLOADED', 'EITHER'])
            ->codes('admin_requirements_state_code', 'state', ['ACTIVE', 'REMOVED'])
            ->check('admin_requirements_reference_by_kind', <<<'SQL'
                (requirement_kind = 'CATALOG_DOCUMENT') = (document_type_code is not null)
                and (requirement_kind = 'EXTERNAL_DOCUMENT') = (evidence_type is not null)
                SQL)
            ->check('admin_requirements_client_original_uploaded', "not requires_client_original or satisfaction_mode = 'UPLOADED'")
            ->check('admin_requirements_removal_columns', "num_nonnulls(removed_by, removed_at, removed_reason) in (0, 3) and (state = 'REMOVED') = (removed_at is not null)")
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate([
                'description', 'is_required', 'satisfaction_mode', 'requires_client_original', 'waiver_eligible', 'is_default_invoice_item',
                'state', 'removed_by', 'removed_at', 'removed_reason', 'lock_version', 'updated_at',
            ]);
    }

    public function down(): void
    {
        Schema::drop('admin_requirements');
    }
};
