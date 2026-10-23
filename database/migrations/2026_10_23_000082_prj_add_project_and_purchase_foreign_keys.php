<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The foreign keys of TASK-005's and TASK-006's columns that reference this Task's tables, added
// expand-only now that the tables exist (DIR-088; DIR-089 (1)): a correction case's project in its company
// and its causal project — an intentional cross-company link (§15) —, a document's project and purchase, a
// version's quotation revision and an evidence link's project, confirmation, purchase, expense and
// requirement, each in the company-scoped form of §15 (C-32). Every column is already indexed: the
// correction case's by 2026_10_20_000014, the documents' by their chains, the evidence links' by IX-08.
return new class extends Migration
{
    /** @var array<string, array{string, list<string>, string, list<string>}> */
    private const KEYS = [
        'correction_cases_c32_project_foreign' => ['correction_cases', ['company_id', 'project_id'], 'projects', ['company_id', 'id']],
        'correction_cases_causal_project_foreign' => ['correction_cases', ['causal_project_id'], 'projects', ['id']],
        'documents_c32_project_foreign' => ['documents', ['company_id', 'project_id'], 'projects', ['company_id', 'id']],
        'documents_c32_purchase_foreign' => ['documents', ['company_id', 'purchase_id'], 'purchases', ['company_id', 'id']],
        'document_versions_c32_quotation_revision_foreign' => ['document_versions', ['company_id', 'quotation_revision_id'], 'quotation_revisions', ['company_id', 'id']],
        'evidence_links_c32_project_foreign' => ['evidence_links', ['company_id', 'project_id'], 'projects', ['company_id', 'id']],
        'evidence_links_c32_commercial_confirmation_foreign' => ['evidence_links', ['company_id', 'commercial_confirmation_id'], 'commercial_confirmations', ['company_id', 'id']],
        'evidence_links_c32_purchase_foreign' => ['evidence_links', ['company_id', 'purchase_id'], 'purchases', ['company_id', 'id']],
        'evidence_links_c32_expense_foreign' => ['evidence_links', ['company_id', 'expense_id'], 'expenses', ['company_id', 'id']],
        'evidence_links_c32_admin_requirement_foreign' => ['evidence_links', ['company_id', 'admin_requirement_id'], 'admin_requirements', ['company_id', 'id']],
    ];

    public function up(): void
    {
        foreach (self::KEYS as $name => [$table, $columns, $references, $referenced]) {
            TableRules::for($table)->foreign($name, $columns, $references, $referenced);
        }

        // A version's rendered revision is looked up by the restricted delete of a DRAFT revision (§20).
        TableRules::for('document_versions')->index('document_versions_quotation_revision', 'quotation_revision_id', 'quotation_revision_id is not null');
    }

    public function down(): void
    {
        DB::statement('drop index document_versions_quotation_revision');

        foreach (array_reverse(self::KEYS) as $name => [$table]) {
            DB::statement("alter table {$table} drop constraint {$name}");
        }
    }
};
