<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.7 `locations` (MD): a rack or location of the one warehouse pool, looked up by its code — no
// WMS (REF-023). Never deleted once referenced; an inactive location stays valid history (§19).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            Columns::id($table);
            $table->text('code')->unique();
            $table->text('name');
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'locations');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('locations')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['name', 'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('locations');
    }
};
