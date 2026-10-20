<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.1 `roles` (CF): the two built-in launch roles, custom roles later (D-01). The rows are
// TASK-011's catalogue.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            Columns::id($table);
            $table->text('code')->unique();
            $table->boolean('is_builtin')->default(false);
            Columns::recordedAt($table);
        });

        TableRules::for('roles')->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('roles');
    }
};
