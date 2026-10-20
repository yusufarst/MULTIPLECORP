<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The framework's session table, which SECURITY AU-06 places in PostgreSQL (D-SEC-02): one row per
// session carrying the user id, so an account's sessions can be ended (RV-05). An infrastructure table
// outside the 125 business tables, never projected raw (PERMISSIONS_MATRIX §7.2); its raw address and
// user agent live only as long as the session (LG-06). No row references a session (LR-03).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->bigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            // No index on the last activity: left to P8's measurement (PERFORMANCE IX-09).
            $table->integer('last_activity');
            $table->index('user_id', 'sessions_ix09_user_id');
        });

        TableRules::for('sessions')->grant('select, insert, update, delete');
    }

    public function down(): void
    {
        Schema::drop('sessions');
    }
};
