<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.3 `client_units` (MD): the unit or department of a client organization, one per name
// within it whatever the case. Its name is S2, searched only with `projects.view` and a company in scope
// (PF-22; PJ-10), by trigram (§21) and by equality and prefix (IX-17).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_units', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'client_organization_id', 'client_organizations');
            $table->text('name');
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'client_units');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('client_units')
            ->uniqueIndex('client_units_name_unique', 'client_organization_id, lower(name)')
            ->trigramIndex('client_units_name_trigram', 'name')
            ->index('client_units_ix17_name_prefix', 'lower(name) text_pattern_ops')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['name', 'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('client_units');
    }
};
