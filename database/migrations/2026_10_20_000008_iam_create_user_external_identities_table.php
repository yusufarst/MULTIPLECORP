<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.1 `user_external_identities` (SR): Google sign-in links (SECURITY AU-22–AU-24). Rows are
// never deleted: an unlink fills its three columns once and relinking adds a row, so the rows are the
// link history. No field beyond these and the routine `id` and `recorded_at`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_external_identities', function (Blueprint $table) {
            Columns::id($table);
            $table->foreignId('user_id');
            $table->text('provider');
            // The provider's stable subject, Google's `sub` — an identifier, not a credential (AU-22).
            $table->text('provider_subject');
            // Display only: never a key, never updated.
            $table->text('email_at_link')->nullable();
            Columns::timestamp($table, 'linked_at');
            Columns::user($table, 'linked_by');
            Columns::timestamp($table, 'unlinked_at')->nullable();
            Columns::user($table, 'unlinked_by')->nullable();
            $table->text('unlink_reason')->nullable();
            Columns::recordedAt($table);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            // IX-18: the account's link history and the unlinks of resets, recovery and deactivation.
            $table->index('user_id', 'user_external_identities_ix18_user');
        });

        TableRules::for('user_external_identities')
            ->codes('user_external_identities_c63_provider', 'provider', ['google'])
            // At most 255 case-sensitive ASCII characters.
            ->check('user_external_identities_subject_format', "provider_subject ~ '^[ -~]{1,255}\$'")
            ->codes('user_external_identities_c63_unlink_reason', 'unlink_reason', ['SELF', 'OWNER', 'DEACTIVATION', 'CREDENTIAL_REISSUE', 'TOTP_DISABLED', 'OWNER_RECOVERY'], nullable: true)
            // In V1 the link is always created by its holder.
            ->check('user_external_identities_c63_linked_by_holder', 'linked_by = user_id')
            // Linked: none of the three unlink columns. Unlinked: time and reason, and an actor exactly
            // when the reason is not OWNER_RECOVERY; the holder for SELF and TOTP_DISABLED, another
            // account for OWNER and CREDENTIAL_REISSUE.
            ->check('user_external_identities_c63_unlink_state', <<<'SQL'
                (unlinked_at is null and unlinked_by is null and unlink_reason is null)
                or (unlinked_at is not null and unlink_reason is not null
                    and (unlinked_by is null) = (unlink_reason = 'OWNER_RECOVERY'))
                SQL)
            ->check('user_external_identities_c63_unlink_actor', <<<'SQL'
                unlink_reason is null
                or unlink_reason not in ('SELF', 'TOTP_DISABLED', 'OWNER', 'CREDENTIAL_REISSUE')
                or (unlink_reason in ('SELF', 'TOTP_DISABLED') and unlinked_by is not null and unlinked_by = user_id)
                or (unlink_reason in ('OWNER', 'CREDENTIAL_REISSUE') and unlinked_by is not null and unlinked_by <> user_id)
                SQL)
            // C-64 and IX-18: one active link per provider subject and per account.
            ->uniqueIndex('user_external_identities_c64_active_subject', 'provider, provider_subject', 'unlinked_at is null')
            ->uniqueIndex('user_external_identities_c64_active_account', 'user_id, provider', 'unlinked_at is null')
            ->grant('select, insert')
            ->grantUpdate(['unlinked_at', 'unlinked_by', 'unlink_reason']);

        // C-63: the three unlink columns change once, from NULL; every other column never changes.
        DB::unprepared(<<<'SQL'
            create or replace function user_external_identities_c63_unlink_once() returns trigger
            language plpgsql as $$
            begin
                if old.unlinked_at is not null or old.unlinked_by is not null or old.unlink_reason is not null then
                    raise exception 'An unlinked external identity never changes (C-63).'
                        using errcode = 'check_violation', constraint = 'user_external_identities_c63_unlink_once';
                end if;

                if (new.id, new.user_id, new.provider, new.provider_subject, new.email_at_link, new.linked_at, new.linked_by, new.recorded_at)
                    is distinct from
                    (old.id, old.user_id, old.provider, old.provider_subject, old.email_at_link, old.linked_at, old.linked_by, old.recorded_at) then
                    raise exception 'Only the three unlink columns of an external identity change (C-63).'
                        using errcode = 'check_violation', constraint = 'user_external_identities_c63_unlink_once';
                end if;

                return new;
            end
            $$;

            create trigger user_external_identities_c63_unlink_once
                before update on user_external_identities
                for each row execute function user_external_identities_c63_unlink_once();
            SQL);
    }

    public function down(): void
    {
        Schema::drop('user_external_identities');
        DB::unprepared('drop function if exists user_external_identities_c63_unlink_once()');
    }
};
