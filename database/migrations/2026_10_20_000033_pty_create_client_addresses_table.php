<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.3 `client_addresses` (MD): a unit's selectable address (AC-02); the address as used is
// snapshotted on the outputs that print it (§14).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_addresses', function (Blueprint $table) {
            Columns::id($table);
            Columns::reference($table, 'client_unit_id', 'client_units');
            $table->text('label')->nullable();
            $table->text('address_lines');
            $table->text('city')->nullable();
            Columns::deactivation($table);
            Columns::commandIdentity($table, 'client_addresses');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
            $table->index('client_unit_id');
        });

        TableRules::for('client_addresses')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['label', 'address_lines', 'city', 'is_active', 'deactivated_at', 'deactivated_by', 'deactivated_reason', 'lock_version', 'updated_at']);
    }

    public function down(): void
    {
        Schema::drop('client_addresses');
    }
};
