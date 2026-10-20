<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.2 `numbering_schemes` (CF): a company's numbering rule per sequence type — the fourteen
// document types, PROJECT and PURCHASE (`DIR-077` CA-09) — from its effective date (WF-MD-01); a version
// draws its number under the scheme in force on its business date, through the company-scoped key, so
// it can only use its own company's scheme (§10; C-32). The exact formats are GAP-019's.
return new class extends Migration
{
    private const SEQUENCE_TYPES = [
        'DOC-01', 'DOC-02', 'DOC-03', 'DOC-04', 'DOC-05', 'DOC-06', 'DOC-07', 'DOC-08', 'DOC-09', 'DOC-10',
        'DOC-11', 'DOC-12', 'DOC-13', 'DOC-14', 'PROJECT', 'PURCHASE',
    ];

    public function up(): void
    {
        Schema::create('numbering_schemes', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->text('sequence_type');
            $table->text('period_granularity');
            $table->text('format_template');
            $table->text('revision_format');
            $table->date('effective_from');
            Columns::commandIdentity($table, 'numbering_schemes');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('numbering_schemes')
            ->unique('numbering_schemes_effective_unique', ['company_id', 'sequence_type', 'effective_from'])
            ->unique('numbering_schemes_c32_company_key', ['company_id', 'id'])
            ->codes('numbering_schemes_sequence_type_code', 'sequence_type', self::SEQUENCE_TYPES)
            ->codes('numbering_schemes_period_granularity_code', 'period_granularity', ['YEAR', 'MONTH'])
            ->codes('numbering_schemes_revision_format_code', 'revision_format', ['NEW_NUMBER', 'BASE_SUFFIX'])
            ->commandIdentity()
            ->grant('select, insert')
            // A scheme not yet in force is edited; one in force is closed by a later scheme (CF, §1).
            ->grantUpdate(['period_granularity', 'format_template', 'revision_format', 'effective_from', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('numbering_schemes');
    }
};
