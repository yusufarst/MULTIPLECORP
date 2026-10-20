<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.1 `users` (MD): individual accounts, never deleted, deactivated instead (§19).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            Columns::id($table);
            Columns::publicId($table);
            $table->text('name');
            $table->text('email');
            // A framework-supported one-way hash only; an unusable password is the hash of an
            // unguessable random value (SECURITY AU-20 c).
            $table->text('password');
            $table->boolean('is_active')->default(true);
            Columns::timestamp($table, 'deactivated_at')->nullable();
            Columns::user($table, 'deactivated_by')->nullable();
            // The four TOTP fields of SECURITY AU-16 and AU-19–AU-21: the secret and the recovery codes
            // are encrypted with the application key; all four are written and cleared together (C-62).
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            Columns::timestamp($table, 'two_factor_confirmed_at')->nullable();
            $table->bigInteger('two_factor_last_step')->nullable();
            // The account's own display language, NULL meaning Indonesian (CAP-14; DIR-077 CA-08).
            $table->text('locale')->nullable();
            Columns::commandIdentity($table, 'users');
            Columns::recordedAt($table);
            Columns::recordedBy($table);
            Columns::editable($table);
        });

        TableRules::for('users')
            ->uniqueIndex('users_email_lower_unique', 'lower(email)')
            ->check('users_c62_totp_fields_together', 'num_nonnulls(two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, two_factor_last_step) in (0, 4)')
            ->codes('users_locale_code', 'locale', ['id', 'en'], nullable: true)
            ->commandIdentity()
            ->grant('select, insert')
            ->grantUpdate([
                'name', 'email', 'password', 'is_active', 'deactivated_at', 'deactivated_by',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step',
                'locale', 'lock_version', 'updated_at',
            ]);
    }

    public function down(): void
    {
        Schema::drop('users');
    }
};
