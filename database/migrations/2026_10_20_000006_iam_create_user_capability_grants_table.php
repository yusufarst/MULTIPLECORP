<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.1 `user_capability_grants` (SR): the separately grantable ADM+ authorities (DIR-024 D-5),
// revoked, never deleted; the rows are the grant history (§19).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_capability_grants', function (Blueprint $table) {
            Columns::id($table);
            $table->foreignId('user_id');
            $table->foreignId('capability_id');
            Columns::user($table, 'granted_by')->nullable();
            Columns::timestamp($table, 'granted_at');
            Columns::user($table, 'revoked_by')->nullable();
            Columns::timestamp($table, 'revoked_at')->nullable();
            $table->text('reason')->nullable();
            Columns::commandIdentity($table, 'user_capability_grants');
            Columns::recordedAt($table);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('capability_id')->references('id')->on('capabilities')->restrictOnDelete();
            $table->index('user_id');
            $table->index('capability_id');
        });

        TableRules::for('user_capability_grants')
            ->uniqueIndex('user_capability_grants_active_unique', 'user_id, capability_id', 'revoked_at is null')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['revoked_by', 'revoked_at']);
    }

    public function down(): void
    {
        Schema::drop('user_capability_grants');
    }
};
