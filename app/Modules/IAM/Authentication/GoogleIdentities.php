<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * SECURITY AU-23 and AU-24's Google identities: the link and its lifecycle, IAM's own (D-SEC-20, D-SEC-21; BD-19;
 * H6-14; RV-16). A link is created by its holder only, in one transaction under the account row lock: the account
 * active with active TOTP; the same subject already linked to it a no-op; a subject linked to another account
 * refused with GOOGLE_LINK_CONFLICT and an alert — by the re-read under the lock, or by the partial UNIQUE key
 * when two accounts race (C-64); an account already linked unlinks first. The e-mail is kept once, for display
 * only. Rows are never deleted: an unlink records its time, reason and actor once (C-63), and relinking adds a
 * row. Each step writes its business audit event in its transaction and its security event after the commit
 * (RG-11; LG-01); a link never grants a role, capability or company scope (AZ-13). Each step may run inside its
 * caller's transaction — a link with RV-16's code acceptance, an unlink inside a deactivation —, its events and
 * alerts then following the outermost commit alone. The account's own and the Owner's controls for them are
 * TASK-023's and TASK-024's.
 */
final class GoogleIdentities
{
    public const PROVIDER = 'google';

    public const LINKED = 'LINKED';

    public const NO_OP = 'NO_OP';

    public const CONFLICT = 'CONFLICT';

    public const ALREADY_LINKED = 'ALREADY_LINKED';

    public const REFUSED = 'REFUSED';

    /** The unlink reasons of AU-24, with the authority each acts under. */
    private const REASONS = [
        'SELF' => AuthorityClass::System,
        'TOTP_DISABLED' => AuthorityClass::System,
        'OWNER' => AuthorityClass::OwnerOnly,
        'CREDENTIAL_REISSUE' => AuthorityClass::OwnerOnly,
        'DEACTIVATION' => AuthorityClass::OwnerOnly,
        'OWNER_RECOVERY' => AuthorityClass::System,
    ];

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SecurityEventRecorder $events,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /** The active account linked to a subject, found by the subject alone — never by e-mail (AU-22, AU-23). */
    public function accountFor(string $subject): ?User
    {
        $userId = DB::table('user_external_identities')->where('provider', self::PROVIDER)
            ->where('provider_subject', $subject)->whereNull('unlinked_at')->value('user_id');

        return is_numeric($userId) ? User::query()->find((int) $userId) : null;
    }

    /** The holder links its own verified Google identity (AU-23); answers what happened. */
    public function link(User $holder, string $subject, ?string $email): string
    {
        $outcome = DB::transaction(function () use ($holder, $subject, $email): string {
            $row = User::query()->lockForUpdate()->find($holder->id);

            return $row instanceof User ? $this->linkLocked($row, $subject, $email) : self::REFUSED;
        });
        $this->announce($holder, $outcome);

        return $outcome;
    }

    /**
     * The link on an account row its caller holds locked in its transaction — PG-065's in the current code's own
     * (AU-23; RV-16): the account active with a confirmed TOTP, the subject and the account re-read, then the insert.
     * Its outcome is announced by `announce` once that transaction commits.
     */
    public function linkLocked(User $row, string $subject, ?string $email): string
    {
        if ($row->getAttribute('is_active') !== true || $row->getAttribute('two_factor_confirmed_at') === null) {
            return self::REFUSED;
        }

        $linked = DB::table('user_external_identities')->where('provider', self::PROVIDER)
            ->where('provider_subject', $subject)->whereNull('unlinked_at')->value('user_id');

        if (is_numeric($linked)) {
            return (int) $linked === $row->id ? self::NO_OP : self::CONFLICT;
        }

        if (DB::table('user_external_identities')->where('user_id', $row->id)->where('provider', self::PROVIDER)->whereNull('unlinked_at')->exists()) {
            return self::ALREADY_LINKED;
        }

        try {
            // A savepoint: C-64's unique violation — another account took the subject between the re-read and the
            // insert — rolls back the insert alone, the caller's transaction going on.
            $id = DB::transaction(fn (): int => DB::table('user_external_identities')->insertGetId([
                'user_id' => $row->id,
                'provider' => self::PROVIDER,
                'provider_subject' => $subject,
                'email_at_link' => $email,
                'linked_at' => now(),
                'linked_by' => $row->id,
            ]));
        } catch (QueryException $e) {
            if ($e->getCode() !== '23505') {
                throw $e;
            }

            return self::CONFLICT;
        }

        $this->audit->record(
            action: 'IAM.GoogleLinked',
            authorityClass: AuthorityClass::System,
            actorUserId: $row->id,
            companyId: null,
            entityType: 'user',
            entityId: $row->id,
            entityPublicId: $row->public_id,
            change: AuditChange::created(['provider' => self::PROVIDER, 'email_at_link' => $email]),
            metadata: ['external_identity' => $id],
        );

        return self::LINKED;
    }

    /** A link's security event and alert, after the outermost commit — never for a rolled-back step (LG-01; LG-07). */
    public function announce(User $holder, string $outcome): void
    {
        match ($outcome) {
            self::LINKED => DB::afterCommit(fn () => $this->linked($holder)),
            self::CONFLICT => DB::afterCommit(fn () => $this->conflict($holder)),
            default => null,
        };
    }

    /**
     * Ends the account's active link with AU-24's reason and actor — the account itself for SELF and
     * TOTP_DISABLED, another account for OWNER, CREDENTIAL_REISSUE and DEACTIVATION, none for OWNER_RECOVERY —;
     * answers whether a link was ended. The sessions the reason ends are its command's to end (AU-10).
     */
    public function unlink(User $account, string $reason, ?int $by, ?int $credentialLink = null, ?string $keepSession = null): bool
    {
        if (! isset(self::REASONS[$reason])) {
            throw new InvalidArgumentException("{$reason} is not an unlink reason of AU-24.");
        }

        // AU-24's actor: the account itself for SELF and TOTP_DISABLED, another account for OWNER, CREDENTIAL_REISSUE
        // and DEACTIVATION, no application account for OWNER_RECOVERY, whose event names the credential link.
        $actorValid = match ($reason) {
            'SELF', 'TOTP_DISABLED' => $by === $account->id,
            'OWNER_RECOVERY' => $by === null && $credentialLink !== null,
            default => $by !== null && $by !== $account->id,
        };

        if (! $actorValid) {
            throw new InvalidArgumentException("The unlink reason {$reason} does not allow this actor (AU-24).");
        }

        $unlinked = DB::transaction(function () use ($account, $reason, $by, $credentialLink, $keepSession): bool {
            $row = User::query()->lockForUpdate()->find($account->id);
            $link = $row instanceof User
                ? DB::table('user_external_identities')->where('user_id', $row->id)->where('provider', self::PROVIDER)->whereNull('unlinked_at')->first(['id'])
                : null;

            if (! $row instanceof User || $link === null) {
                return false;
            }

            DB::table('user_external_identities')->where('id', $link->id)->update(['unlinked_at' => now(), 'unlinked_by' => $by, 'unlink_reason' => $reason]);
            $this->audit->record(
                action: 'IAM.GoogleUnlinked',
                authorityClass: self::REASONS[$reason],
                actorUserId: $by,
                companyId: null,
                entityType: 'user',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                reason: $reason,
                change: AuditChange::updated(['unlinked' => false], ['unlinked' => true]),
                metadata: array_filter(['external_identity' => $link->id, 'credential_link' => $credentialLink], fn (mixed $value): bool => $value !== null),
            );

            // SELF: the holder's other sessions end last, in the same transaction (AU-24; RV-16).
            if ($keepSession !== null) {
                DB::table('sessions')->where('user_id', $row->id)->where('id', '!=', $keepSession)->delete();
            }

            return true;
        });

        if ($unlinked) {
            DB::afterCommit(fn () => $this->events->record(SecurityEventKind::GoogleUnlinked, userId: $account->id, actorUserId: $by, reasonCode: $reason));
        }

        return $unlinked;
    }

    /**
     * An Owner's unlink inside its NX-02 command (AU-24 OWNER, DEACTIVATION, CREDENTIAL_REISSUE; RV-05; RV-15), on the
     * account row the command's envelope holds at LK-03: a conditional update of the active row's unlink columns
     * (C-63), its audit event naming the command and the Owner's reason. The command writes GOOGLE_UNLINKED after its
     * commit — and LG-07's alert for an OWNER-role account. Answers whether a link was active.
     */
    public function unlinkHeld(User $account, string $reason, int $by, string $commandId, ?string $ownerReason = null): bool
    {
        if (! in_array($reason, ['OWNER', 'DEACTIVATION', 'CREDENTIAL_REISSUE'], true) || $by === $account->id) {
            throw new InvalidArgumentException("The unlink reason {$reason} does not allow this actor (AU-24).");
        }

        $link = DB::table('user_external_identities')->where('user_id', $account->id)->where('provider', self::PROVIDER)->whereNull('unlinked_at')->first(['id']);

        if ($link === null || DB::table('user_external_identities')->where('id', $link->id)->whereNull('unlinked_at')
            ->update(['unlinked_at' => now(), 'unlinked_by' => $by, 'unlink_reason' => $reason]) !== 1) {
            return false;
        }

        $this->audit->record(
            action: 'IAM.GoogleUnlinked',
            authorityClass: self::REASONS[$reason],
            actorUserId: $by,
            companyId: null,
            entityType: 'user',
            entityId: $account->id,
            entityPublicId: $account->public_id,
            reason: $ownerReason ?? $reason,
            commandId: $commandId,
            change: AuditChange::updated(['unlinked' => false], ['unlinked' => true]),
            metadata: ['external_identity' => $link->id, 'unlink_reason' => $reason],
        );

        return true;
    }

    private function linked(User $holder): void
    {
        $this->events->record(SecurityEventKind::GoogleLinked, userId: $holder->id, actorUserId: $holder->id);

        if ($this->capabilities->requiresTotp($holder->id)) {
            Log::alert('A Google identity was linked to a high-risk account (AU-23; LG-07).', ['user_id' => $holder->id]);
        }
    }

    private function conflict(User $holder): void
    {
        $this->events->record(SecurityEventKind::GoogleLinkConflict, userId: $holder->id, actorUserId: $holder->id);
        Log::alert('A Google subject linked to another account was refused (AU-23; LG-07).', ['user_id' => $holder->id]);
    }
}
