<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.10 `evidence_documents` (SR) and §10: an authentic external original — never generated
// (FS-12) — with its declared issuer, the issuer's reference, its date and the identity key of L-45, so
// re-attaching the same identity versions it and never duplicates it (C-49). Pool evidence of shared
// stock belongs to no company and is one of three types, fabricating no ownership (DIR-027; C-58). The
// type and the scope are keyed with the id, so an amount-bearing citation carries the type its role
// accepts and a link carries the scope (C-58). The type-free identity index of the cross-type duplicate
// warning keys on the issuer as TASK-030 normalizes it, which no document states yet (TASK-006's
// discovery (2)).
return new class extends Migration
{
    private const TYPES = [
        'CLIENT_SPK', 'CLIENT_ORDER', 'SIPLAH_ORDER', 'THIRD_PARTY_HPS', 'TAX_INVOICE', 'NPWP', 'NIB', 'BANK_STATEMENT',
        'TAX_WITHHOLDING_PROOF', 'TAX_COLLECTION_PROOF', 'TAX_PAYMENT_PROOF', 'FEE_ADVICE', 'SUPPLIER_BILL', 'DELIVERY_PROOF',
        'PAYMENT_PROOF', 'TRANSFER_PROOF', 'WAREHOUSE_RECORD', 'OPENING_SIGNOFF', 'LEGACY_DOCUMENT', 'OTHER',
    ];

    public function up(): void
    {
        Schema::create('evidence_documents', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->text('evidence_scope');
            Columns::reference($table, 'company_id', 'companies')->nullable();
            $table->text('evidence_type');
            $table->text('declared_issuer');
            $table->text('issuer_reference')->nullable();
            $table->date('document_date');
            // The normalized issuer, type, reference and date (L-45).
            $table->text('identity_key');
            Columns::money($table, 'proven_amount')->nullable();
            $table->text('deliverer')->nullable();
            $table->date('received_date')->nullable();
            // Keyed to the document's own versions with them (2026_10_20_000047).
            $table->bigInteger('current_version_id')->nullable();
            Columns::commandIdentity($table, 'evidence_documents');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('evidence_documents')
            ->codes('evidence_documents_scope_code', 'evidence_scope', ['COMPANY', 'POOL'])
            ->codes('evidence_documents_type_code', 'evidence_type', self::TYPES)
            ->check('evidence_documents_c58_pool_has_no_company', "(evidence_scope = 'POOL') = (company_id is null)")
            ->check('evidence_documents_c58_pool_types', "evidence_scope = 'COMPANY' or evidence_type in ('WAREHOUSE_RECORD', 'OPENING_SIGNOFF', 'OTHER')")
            ->check('evidence_documents_proven_amount_not_negative', 'proven_amount is null or proven_amount >= 0')
            // Pool evidence, of no company, is one scope of its own, where an identity is registered once too.
            ->uniqueIndex('evidence_documents_c49_identity', 'company_id, evidence_type, identity_key', 'issuer_reference is not null', nullsDistinct: false)
            ->unique('evidence_documents_c58_type_key', ['id', 'evidence_type'])
            ->unique('evidence_documents_c58_scope_key', ['id', 'evidence_scope'])
            ->unique('evidence_documents_c32_company_key', ['company_id', 'id'])
            ->commandIdentity()
            ->grant('select, insert')
            // A replacement moves the current version (L-41); everything else stays as registered.
            ->grantUpdate(['current_version_id', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('evidence_documents');
    }
};
