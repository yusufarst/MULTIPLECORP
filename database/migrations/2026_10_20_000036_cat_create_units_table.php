<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.4 `units` (MD): the unit dictionary. Never deleted once referenced, made inactive instead
// (§19 "Unit, location"; §13 ARCHIVE / INACTIVE).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            Columns::id($table);
            $table->text('code');
            $table->text('name');
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'units');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('units')
            ->unique('units_code_unique', ['code'])
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['name', 'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('units');
    }
};
