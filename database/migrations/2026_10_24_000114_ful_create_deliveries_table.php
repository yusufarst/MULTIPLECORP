<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.9 `deliveries` (AF): the client-side record of goods handed to the client, one per command,
// never touching stock (BR-INV-02; FS-03); its project is its company's (C-32). DOC-08 and DOC-10 render it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            Columns::reference($table, 'company_id', 'companies');
            $table->bigInteger('project_id');
            Columns::businessDate($table);
            $table->text('recipient_name');
            $table->text('condition_note')->nullable();
            Columns::commandIdentity($table, 'deliveries');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            $table->index('project_id');
        });

        TableRules::for('deliveries')
            ->unique('deliveries_c32_company_key', ['company_id', 'id'])
            ->unique('deliveries_one_per_command', ['command_id'])
            ->foreign('deliveries_c32_project_foreign', ['company_id', 'project_id'], 'projects', ['company_id', 'id'])
            ->commandIdentity()
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('deliveries');
    }
};
