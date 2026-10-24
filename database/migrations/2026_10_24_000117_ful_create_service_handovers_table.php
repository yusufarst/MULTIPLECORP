<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.9 `service_handovers` (AF): the SERVICE equivalent of a delivery (L-39), one per command; its
// project is its company's (C-32). DOC-08 renders it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_handovers', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->bigInteger('project_id');
            Columns::businessDate($table);
            $table->text('recipient_name');
            Columns::commandIdentity($table, 'service_handovers');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('project_id');
        });

        TableRules::for('service_handovers')
            ->unique('service_handovers_c32_company_key', ['company_id', 'id'])
            ->unique('service_handovers_one_per_command', ['command_id'])
            ->foreign('service_handovers_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'])
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('service_handovers');
    }
};
