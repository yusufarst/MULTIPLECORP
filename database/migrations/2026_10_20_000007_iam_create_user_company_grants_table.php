<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.1 `user_company_grants` (SR): default deny — no active row, no access to the company
// (BR-ACC-01). `company_id` references `companies`, which TASK-006 creates.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_company_grants', function (Blueprint $table) {
            Columns::id($table);
            $table->foreignId('user_id');
            $table->bigInteger('company_id');
            Columns::user($table, 'granted_by')->nullable();
            Columns::timestamp($table, 'granted_at');
            Columns::user($table, 'revoked_by')->nullable();
            Columns::timestamp($table, 'revoked_at')->nullable();
            Columns::commandIdentity($table, 'user_company_grants');
            Columns::recordedAt($table);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index('user_id');
            $table->index('company_id');
        });

        TableRules::for('user_company_grants')
            ->uniqueIndex('user_company_grants_active_unique', 'user_id, company_id', 'revoked_at is null')
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate(['revoked_by', 'revoked_at']);
    }

    public function down(): void
    {
        Schema::drop('user_company_grants');
    }
};
