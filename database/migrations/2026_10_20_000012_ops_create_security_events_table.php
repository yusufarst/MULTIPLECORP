<?php

use App\Support\Database\Columns;
use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATABASE §4.13 `security_events` (AE): the security log, kept apart from the business audit (OB §36),
// its kinds and fields those of SECURITY LG-02. A login identifier and an address are stored only as
// keyed hashes (LG-03); never a password, token or secret.
return new class extends Migration
{
    /** SECURITY LG-02's 39 kinds: DATABASE's seven starters, the twelve and the two of TECH-022 it adds, and the authentication amendment's eighteen. */
    private const KINDS = [
        'LOGIN_SUCCEEDED', 'LOGIN_FAILED', 'THROTTLED', 'LOGOUT', 'SESSION_INVALIDATED',
        'PASSWORD_RESET_REQUESTED', 'PASSWORD_RESET_COMPLETED',
        'ACCOUNT_SETUP_LINK_ISSUED', 'ACCOUNT_SETUP_COMPLETED', 'OWNER_RECOVERY_LINK_ISSUED', 'CREDENTIAL_LINK_REJECTED',
        'PASSWORD_CHANGED', 'PASSWORD_CONFIRMATION_FAILED', 'ACCESS_DENIED', 'REQUEST_FORGERY_REJECTED', 'UPLOAD_REJECTED',
        'EXPORT_REQUESTED', 'EXPORT_DOWNLOADED', 'AUTHORIZATION_ANOMALY',
        'EVIDENCE_DUPLICATE_UNDISCLOSED', 'IMPORT_COLLISION_UNDISCLOSED',
        'TWO_FACTOR_FAILED', 'TWO_FACTOR_COOLDOWN_STARTED', 'TWO_FACTOR_COOLDOWN_REFUSED', 'OWNER_RESTORED',
        'TOTP_ENROLLED', 'TOTP_REPLACED', 'TOTP_RESET', 'TOTP_DISABLED', 'CREDENTIALS_VOIDED',
        'RECOVERY_CODE_USED', 'RECOVERY_CODES_VIEWED', 'RECOVERY_CODES_REGENERATED',
        'GOOGLE_SIGNIN_FAILED', 'GOOGLE_LINKED', 'GOOGLE_UNLINKED', 'GOOGLE_LINK_CONFLICT',
        'PASSWORD_CHECK_SKIPPED', 'TOTP_SECRETS_REENCRYPTED',
    ];

    /** The two kinds whose typed columns are the stored source of two Owner-review items (TECH-022; GAP-034). */
    private const UNDISCLOSED = "event_kind in ('EVIDENCE_DUPLICATE_UNDISCLOSED', 'IMPORT_COLLISION_UNDISCLOSED')";

    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            Columns::id($table);
            Columns::timestamp($table, 'occurred_at')->useCurrent();
            $table->text('event_kind');
            // The account concerned, and the acting account when different (LG-02).
            Columns::user($table, 'user_id')->nullable();
            Columns::user($table, 'actor_user_id')->nullable();
            $table->text('reason_code')->nullable();
            $table->text('route_name')->nullable();
            $table->uuid('target_public_id')->nullable();
            $table->text('identifier_hash')->nullable();
            $table->text('ip_hash')->nullable();
            $table->text('user_agent_summary')->nullable();
            $table->text('correlation_id')->nullable();
            // The small whitelisted details object of LG-02.
            $table->jsonb('details')->nullable();
            // TECH-022: plain values without foreign keys, NULL for every other kind.
            $table->uuid('command_id')->nullable();
            $table->text('target_entity_type')->nullable();
            $table->text('related_entity_type')->nullable();
            $table->uuid('related_public_id')->nullable();
            $table->bigInteger('company_id')->nullable();
            $table->bigInteger('related_company_id')->nullable();
            Columns::recordedAt($table);
        });

        TableRules::for('security_events')
            ->codes('security_events_kind', 'event_kind', self::KINDS)
            // The typed columns carry the command, the target and the matched record of those two kinds,
            // each side's company where it has one, and stay empty on every other kind.
            ->check('security_events_undisclosed_columns', '(not ('.self::UNDISCLOSED.')'
                .' and num_nonnulls(command_id, target_entity_type, related_entity_type, related_public_id, company_id, related_company_id) = 0)'
                .' or ('.self::UNDISCLOSED.' and command_id is not null and target_entity_type is not null'
                .' and target_public_id is not null and related_entity_type is not null and related_public_id is not null)')
            ->uniqueIndex('security_events_undisclosed_unique', 'event_kind, command_id, related_public_id', self::UNDISCLOSED)
            // IX-14: the Owner-review signals; IX-19: the durable floor of the second-factor limit (AU-05).
            ->index('security_events_ix14_owner_review', 'occurred_at', "event_kind in ('AUTHORIZATION_ANOMALY', 'EVIDENCE_DUPLICATE_UNDISCLOSED', 'IMPORT_COLLISION_UNDISCLOSED')")
            ->index('security_events_ix19_two_factor', 'user_id, occurred_at', "event_kind in ('TWO_FACTOR_FAILED', 'TWO_FACTOR_COOLDOWN_STARTED')")
            ->grant('select, insert');
    }

    public function down(): void
    {
        Schema::drop('security_events');
    }
};
