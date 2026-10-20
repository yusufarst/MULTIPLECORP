<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.1 `user_roles` (SR): the account's launch role, one per user in V1 (an application rule). A
// role change — an Owner's demotion (TASK-024; L-34) or the OWNER-restoration command (RV-18) — updates
// the account's one row in place, its before and after in the audit event (DATABASE §13); no row is
// deleted and no other column changes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id');
            $table->foreignId('role_id');
            Columns::user($table, 'granted_by')->nullable();
            Columns::timestamp($table, 'granted_at');
            Columns::commandIdentity($table, 'user_roles');
            Columns::recordedAt($table);
            $table->primary(['user_id', 'role_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
            // The Owner set of LK-02: which accounts hold a role.
            $table->index('role_id');
        });

        TableRules::for('user_roles')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['role_id', 'granted_by', 'granted_at']);
    }

    public function down(): void
    {
        Schema::drop('user_roles');
    }
};
