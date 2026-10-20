<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.10 `document_types` (CF): the bounded catalogue of the fourteen documents, keyed by their
// code (V1_SCOPE; CAP-08). Its rows are not written here: the codes of `subject_kind` and each type's
// numbering and modes are not yet stated (TASK-006's discovery (3)).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->text('code')->primary();
            $table->text('name');
            $table->text('subject_kind');
            $table->boolean('is_numbered');
            $table->rawColumn('allowed_modes', 'text[]');
            Columns::recordedAt($table);
        });

        TableRules::for('document_types')
            ->codes('document_types_code_catalogue', 'code', [
                'DOC-01', 'DOC-02', 'DOC-03', 'DOC-04', 'DOC-05', 'DOC-06', 'DOC-07', 'DOC-08', 'DOC-09', 'DOC-10',
                'DOC-11', 'DOC-12', 'DOC-13', 'DOC-14',
            ])
            ->check('document_types_allowed_modes', "cardinality(allowed_modes) >= 1 and allowed_modes <@ array['STANDARD', 'RECEIPT', 'PRE_PAYMENT']::text[]")
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('document_types');
    }
};
