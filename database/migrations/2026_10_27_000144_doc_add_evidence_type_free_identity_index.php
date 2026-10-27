<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// DIR-089 (2) (TASK-030): DATABASE §4.10's non-unique index on the type-free evidence identity — company, normalized
// issuer, reference and date —, in §4.10's column order. Its issuer and reference are the first and third parts of
// `identity_key`, whose normalization `EvidenceIdentity` defines once (L-45), so the index and the key cannot
// normalize apart. It serves FL-10's cross-type warning, which probes it once per company and once for pool evidence
// (BD-15; AS-08), and the reference-less duplicate of BD-14, whose reference is empty. Expand-only; no row is touched.
return new class extends Migration
{
    private const NAME = 'evidence_documents_type_free_identity';

    public function up(): void
    {
        TableRules::for('evidence_documents')->index(self::NAME, "company_id, split_part(identity_key, '|', 1), split_part(identity_key, '|', 3), document_date");
    }

    public function down(): void
    {
        DB::statement('drop index '.self::NAME);
    }
};
