<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.1 `role_capabilities` (CF): which capabilities a role holds; authorization checks
// capabilities, never role names.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_capabilities', function (Blueprint $table) {
            $table->foreignId('role_id');
            $table->foreignId('capability_id');
            Columns::recordedAt($table);
            $table->primary(['role_id', 'capability_id']);
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('capability_id')->references('id')->on('capabilities')->restrictOnDelete();
            $table->index('capability_id');
        });

        TableRules::for('role_capabilities')->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('role_capabilities');
    }
};
