<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.5 `commercial_confirmations` (AD) and §9: the commercial confirmation of a project (BR-PRJ-06)
// — an approved quotation revision, a client order without a quotation, or an open project's migration —,
// written with its pins (AX-13). One root confirmation per revision (C-49), correctable only by a linear
// chain of superseding confirmations of the same authority (CM-27; L-32; C-59). The client's order evidence
// is an `evidence_links` row.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_confirmations', function (Blueprint $table) {
            Columns::id($table);
            $table->bigInteger('project_id');
            $table->bigInteger('company_id');
            $table->text('kind');
            $table->bigInteger('quotation_revision_id')->nullable();
            Columns::businessDate($table);
            Columns::user($table, 'decided_by');
            $table->text('reason')->nullable();
            $table->bigInteger('supersedes_confirmation_id')->nullable();
            Columns::commandIdentity($table, 'commercial_confirmations');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('quotation_revision_id');
        });

        TableRules::for('commercial_confirmations')
            ->unique('commercial_confirmations_c32_company_key', ['company_id', 'id'])
            ->unique('commercial_confirmations_project_key', ['project_id', 'id'])
            ->unique('commercial_confirmations_c59_linear_chain', ['supersedes_confirmation_id'])
            // One command confirms once (§4.5; AX-13).
            ->unique('commercial_confirmations_one_per_command', ['command_id'])
            ->uniqueIndex('commercial_confirmations_c49_one_root_per_revision', 'quotation_revision_id', 'supersedes_confirmation_id is null')
            ->foreign('commercial_confirmations_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'])
            ->foreign('commercial_confirmations_revision_foreign', ['project_id', 'quotation_revision_id'], 'quotation_revisions', ['project_id', 'id'])
            ->foreign('commercial_confirmations_c59_supersedes_foreign', ['project_id', 'supersedes_confirmation_id'], 'commercial_confirmations', ['project_id', 'id'])
            ->codes('commercial_confirmations_kind_code', 'kind', ['QUOTATION_APPROVAL', 'NO_QUOTATION', 'MIGRATION'])
            ->check('commercial_confirmations_revision_by_kind', "(kind = 'QUOTATION_APPROVAL') = (quotation_revision_id is not null)")
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('commercial_confirmations');
    }
};
