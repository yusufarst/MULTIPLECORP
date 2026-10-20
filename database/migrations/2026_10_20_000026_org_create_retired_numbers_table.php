<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.2 `retired_numbers` (AF): the tombstone of a project number whose DRAFT project was
// discarded (L-37), written in the discarding transaction, so a retired number is recorded, explained and
// never reissued; document numbers need none — a number is drawn only at issue and a void keeps its
// version row (§10; C-10).
return new class extends Migration
{
    private const SEQUENCE_TYPES = [
        'DOC-01', 'DOC-02', 'DOC-03', 'DOC-04', 'DOC-05', 'DOC-06', 'DOC-07', 'DOC-08', 'DOC-09', 'DOC-10',
        'DOC-11', 'DOC-12', 'DOC-13', 'DOC-14', 'PROJECT', 'PURCHASE',
    ];

    public function up(): void
    {
        Schema::create('retired_numbers', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->text('sequence_type');
            $table->text('period_key');
            $table->bigInteger('sequence_no');
            $table->text('formatted_number');
            $table->text('retired_kind');
            // The discarded record's public identifier.
            $table->uuid('source_public_id');
            $table->text('reason');
            Columns::user($table, 'retired_by');
            Columns::commandIdentity($table, 'retired_numbers');
            Columns::recordedAt($table);
        });

        TableRules::for('retired_numbers')
            ->unique('retired_numbers_c10_number', ['company_id', 'sequence_type', 'period_key', 'sequence_no'])
            ->unique('retired_numbers_c10_formatted_number', ['company_id', 'sequence_type', 'formatted_number'])
            ->unique('retired_numbers_c32_company_key', ['company_id', 'id'])
            ->codes('retired_numbers_sequence_type_code', 'sequence_type', self::SEQUENCE_TYPES)
            ->check('retired_numbers_period_key_format', "period_key ~ '^[0-9]{4}(-(0[1-9]|1[0-2]))?$'")
            ->codes('retired_numbers_retired_kind_code', 'retired_kind', ['DISCARDED_DRAFT_PROJECT'])
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('retired_numbers');
    }
};
