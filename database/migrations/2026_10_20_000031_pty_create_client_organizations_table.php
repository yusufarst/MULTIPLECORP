<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.3 `client_organizations` (MD): one generic hierarchy, UNY and UGM ordinary rows (OB §8).
// No UNIQUE on the name — same-named schools in different cities are legitimate; search-first creation
// and a warning prevent duplicates (WF-MD-02). The name is searched by trigram (§21) and looked up by
// equality and prefix on its lower-cased form (IX-17; PF-21).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_organizations', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->text('name');
            $table->text('npwp')->nullable();
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'client_organizations');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('client_organizations')
            ->trigramIndex('client_organizations_name_trigram', 'name')
            ->index('client_organizations_ix17_name_prefix', 'lower(name) text_pattern_ops')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['name', 'npwp', 'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('client_organizations');
    }
};
