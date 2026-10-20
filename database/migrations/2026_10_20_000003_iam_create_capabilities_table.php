<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.1 `capabilities` (CF): the capability catalogue of PERMISSIONS_MATRIX, with each
// capability's authority class (C-40, whose behaviour TASK-011 proves). The rows are TASK-011's.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capabilities', function (Blueprint $table) {
            Columns::id($table);
            $table->text('code')->unique();
            $table->text('authority_class');
            Columns::recordedAt($table);
        });

        TableRules::for('capabilities')
            ->codes('capabilities_c40_authority_class', 'authority_class', ['ADM', 'ADM_PLUS', 'OWNER_ONLY'])
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('capabilities');
    }
};
