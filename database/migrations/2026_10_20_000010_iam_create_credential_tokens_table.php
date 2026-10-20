<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The application's credential-token store of SECURITY AU-11: one row per credential link, bound to one
// account and one purpose, single-use, kept only as a keyed hash of its token. An infrastructure table
// outside the 125 business tables, never projected raw (PERMISSIONS_MATRIX §7.2); its rows are the
// lock class LK-04. Issuing, consuming and purging links are TASK-017's.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_tokens', function (Blueprint $table) {
            Columns::id($table);
            $table->foreignId('user_id');
            $table->text('purpose');
            // The issuing Owner's account; an OWNER_RECOVERY link records no application issuer (AU-14).
            Columns::user($table, 'issued_by')->nullable();
            $table->text('token_hash');
            Columns::timestamp($table, 'issued_at')->useCurrent();
            Columns::timestamp($table, 'expires_at');
            Columns::timestamp($table, 'used_at')->nullable();
            Columns::timestamp($table, 'revoked_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        TableRules::for('credential_tokens')
            ->codes('credential_tokens_purpose_code', 'purpose', ['ONBOARDING', 'RESET', 'OWNER_RECOVERY'])
            ->check('credential_tokens_issuer', "(issued_by is null) = (purpose = 'OWNER_RECOVERY')")
            // A link ends once: by its use or by its revocation.
            ->check('credential_tokens_used_or_revoked', 'num_nonnulls(used_at, revoked_at) <= 1')
            // IX-04 (H6-11): the token hash once; one unused, unrevoked link per account and purpose, so a
            // reissue revokes the old link in the same transaction; the expiry for the purge.
            ->uniqueIndex('credential_tokens_ix04_token_hash', 'token_hash')
            ->uniqueIndex('credential_tokens_ix04_open_link', 'user_id, purpose', 'used_at is null and revoked_at is null')
            ->index('credential_tokens_ix04_expires_at', 'expires_at')
            ->grant('select, insert, delete')
            ->grantUpdate(['used_at', 'revoked_at']);
    }

    public function down(): void
    {
        Schema::drop('credential_tokens');
    }
};
