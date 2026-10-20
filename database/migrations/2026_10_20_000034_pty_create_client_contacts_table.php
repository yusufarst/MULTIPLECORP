<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.3 `client_contacts` (MD): a unit's person in charge (PIC), an S2 relation; the contact as
// used is snapshotted on the outputs that print it (§14).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'client_unit_id', 'client_units');
            $table->text('name');
            $table->text('position')->nullable();
            $table->text('phone')->nullable();
            $table->text('email')->nullable();
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'client_contacts');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
            $table->index('client_unit_id');
        });

        TableRules::for('client_contacts')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['name', 'position', 'phone', 'email', 'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('client_contacts');
    }
};
