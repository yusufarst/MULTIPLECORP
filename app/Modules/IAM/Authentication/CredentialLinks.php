<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * SECURITY AU-11–AU-12's credential links (DEP-08; D-SEC-05), IAM's own broker — never the framework's password
 * broker, token table or path-token URL: a token of 256 random bits kept only as a keyed hash, bound to one account
 * and one purpose, single-use with atomic consumption, expiring after 24 hours (ONBOARDING), 60 minutes (RESET) or
 * 30 minutes (OWNER_RECOVERY), voided on reissue and on any password change. Consuming a link sets the password in
 * one transaction with its business audit event — the account as actor under SYSTEM, or no actor with the link
 * named for OWNER_RECOVERY (RG-11; CI-09) —, then ends the account's sessions and records the security event after
 * the commit (LG-01); a link never signs anyone in (AU-11). An OWNER_RECOVERY link's use also clears the TOTP fields
 * and unlinks Google in that transaction (AU-14; AU-20 e). Issuing is TASK-024's and TASK-020's command, its step-up
 * and audit theirs; `issue` is the store's half they call.
 */
final class CredentialLinks
{
    public const LIFETIMES = ['ONBOARDING' => 24 * 60, 'RESET' => 60, 'OWNER_RECOVERY' => 30];

    /** The four TOTP fields, cleared together (C-62). */
    public const CLEARED_TOTP = ['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null];

    public function __construct(
        private readonly SessionTerminator $sessions,
        private readonly SecurityEventRecorder $events,
        private readonly AuditRecorder $audit,
        private readonly VerificationSlots $slots,
        private readonly GoogleIdentities $identities,
    ) {}

    /** A new link's token, shown once; the account's open link of the same purpose is revoked first (AU-11). */
    public function issue(User $account, string $purpose, ?int $issuedBy): string
    {
        return $this->issueLink($account, $purpose, $issuedBy)['token'];
    }

    /**
     * A new link's token with its store identifier, which the issue's audit and security events name (AU-14).
     *
     * @return array{token: string, id: int}
     */
    public function issueLink(User $account, string $purpose, ?int $issuedBy): array
    {
        if (! isset(self::LIFETIMES[$purpose])) {
            throw new InvalidArgumentException("{$purpose} is not a credential-link purpose (AU-11).");
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = CarbonImmutable::now();

        $id = DB::transaction(function () use ($account, $purpose, $issuedBy, $token, $now): int {
            DB::table('credential_tokens')->where('user_id', $account->id)->where('purpose', $purpose)
                ->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => $now]);

            return DB::table('credential_tokens')->insertGetId([
                'user_id' => $account->id,
                'purpose' => $purpose,
                'issued_by' => $purpose === 'OWNER_RECOVERY' ? null : $issuedBy,
                'token_hash' => self::hash($token),
                'issued_at' => $now,
                'expires_at' => $now->addMinutes(self::LIFETIMES[$purpose]),
            ]);
        });

        return ['token' => $token, 'id' => $id];
    }

    /**
     * Sets the password from a link, or answers null for an unknown, expired, used or revoked one alike (AU-12).
     * The password passed the profile already — its range check outside this transaction (H6-15). As H6-11 orders
     * it: the link looked up without a lock, the new hash computed within a slot of H6-12 outside any transaction,
     * then one transaction — the account's row locked before the link's (D-CC-03), the link marked used only while
     * it is unused, unrevoked and unexpired, so two concurrent submissions cannot both set a password.
     *
     * @throws VerificationRefused
     */
    public function consume(string $token, string $password, ?string $ipAddress, ?string $userAgent): ?User
    {
        $link = $this->live($token);
        $hash = $link === null ? null : $this->slots->unauthenticated('password.set', fn (): string => Hash::make($password));

        $used = $link === null || $hash === null ? null : DB::transaction(function () use ($link, $hash) {
            $account = User::query()->lockForUpdate()->find($link->user_id);

            if (! $account instanceof User || ! $account->is_active) {
                return null;
            }

            $now = now();
            $marked = DB::table('credential_tokens')->where('id', $link->id)
                ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', $now)
                ->update(['used_at' => $now]);

            if ($marked !== 1) {
                return null;
            }

            // Any password change voids every other open link of the account (AU-11).
            DB::table('credential_tokens')->where('user_id', $account->id)->where('id', '!=', $link->id)
                ->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => $now]);
            $before = $account->password;
            $recovery = $link->purpose === 'OWNER_RECOVERY';
            $hadTotp = $account->two_factor_confirmed_at !== null;
            // Only an OWNER_RECOVERY link resets TOTP: its four fields cleared with the password (AU-11; AU-20 e; C-62).
            $account->forceFill(['password' => $hash, ...($recovery ? self::CLEARED_TOTP : [])])->save();

            $this->audit->record(
                action: 'IAM.CredentialLinkConsumed',
                authorityClass: AuthorityClass::System,
                actorUserId: $recovery ? null : $account->id,
                companyId: null,
                entityType: 'user',
                entityId: $account->id,
                entityPublicId: $account->public_id,
                // The password's place in the before/after, its values redacted (RG-11; LG-03).
                change: AuditChange::updated(['password' => $before], ['password' => $hash]),
                metadata: ['purpose' => $link->purpose, 'credential_link' => $link->id],
            );

            if ($recovery) {
                $this->recovered($account, $link->id, $hadTotp);
            }

            return [$account, $link->purpose, $hadTotp];
        });

        if ($used === null) {
            $this->rejected($ipAddress, $userAgent);

            return null;
        }

        [$account, $purpose, $hadTotp] = $used;
        $this->sessions->endAll($account->id);
        $kind = $purpose === 'ONBOARDING' ? SecurityEventKind::AccountSetupCompleted : SecurityEventKind::PasswordResetCompleted;
        $this->events->record($kind, userId: $account->id, reasonCode: $purpose, routeName: 'password.set', ipAddress: $ipAddress, userAgent: $userAgent);

        if ($purpose === 'OWNER_RECOVERY') {
            if ($hadTotp) {
                $this->events->record(SecurityEventKind::TotpReset, userId: $account->id, reasonCode: $purpose, routeName: 'password.set');
            }

            // Every AU-20 event of an OWNER-role account alerts, the PASSWORD_RESET_COMPLETED of path (e) among them (LG-07).
            Log::alert('An OWNER_RECOVERY link was used: password set, TOTP cleared, sessions ended, Google unlinked (AU-14; AU-20 e; LG-07).', ['user_id' => $account->id]);
        }

        return $account;
    }

    /**
     * The rest of AU-20 e's use, in the link's transaction: the TOTP reset's audit event naming the link, no actor, and
     * the Google identity unlinked with reason OWNER_RECOVERY and `unlinked_by` NULL, its event naming the same link
     * (AU-14; AU-24). The sessions end after the commit, with every other link already revoked above.
     */
    private function recovered(User $account, int $linkId, bool $hadTotp): void
    {
        if ($hadTotp) {
            $this->audit->record(
                action: 'IAM.TotpReset',
                authorityClass: AuthorityClass::System,
                actorUserId: null,
                companyId: null,
                entityType: 'user',
                entityId: $account->id,
                entityPublicId: $account->public_id,
                reason: 'OWNER_RECOVERY',
                change: AuditChange::updated(['second_factor_active' => true], ['second_factor_active' => false]),
                metadata: ['credential_link' => $linkId],
            );
        }

        $this->identities->unlink($account, 'OWNER_RECOVERY', null, credentialLink: $linkId);
    }

    /**
     * The account of a link that can still be used, for AU-03's personal-text rule; none for an unknown, expired,
     * used or revoked link, which gets MSG-28 before any rule is checked (AU-12).
     */
    public function accountFor(string $token): ?User
    {
        $link = $this->live($token);

        return $link === null ? null : User::query()->find($link->user_id);
    }

    /** CREDENTIAL_LINK_REJECTED for a link that sets no password, with nothing that tells its cases apart (AU-12; LG-02). */
    public function rejected(?string $ipAddress, ?string $userAgent): void
    {
        $this->events->record(SecurityEventKind::CredentialLinkRejected, routeName: 'password.set', ipAddress: $ipAddress, userAgent: $userAgent);
    }

    /** Purges the links past their expiry — used, revoked or never used alike (AU-11; H9-04); answers how many. */
    public function purgeExpired(): int
    {
        return DB::table('credential_tokens')->where('expires_at', '<=', now())->delete();
    }

    /** @return object{id: int, user_id: int, purpose: string}|null */
    private function live(string $token): ?object
    {
        $link = DB::table('credential_tokens')->where('token_hash', self::hash($token))
            ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())
            ->first(['id', 'user_id', 'purpose']);

        if ($link === null || ! is_numeric($link->id) || ! is_numeric($link->user_id) || ! is_string($link->purpose)) {
            return null;
        }

        return (object) ['id' => (int) $link->id, 'user_id' => (int) $link->user_id, 'purpose' => $link->purpose];
    }

    /** The keyed hash a token is kept and limited by — never the raw token (AU-11, AU-12). */
    public static function hash(string $token): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $token, is_string($key) ? $key : '');
    }
}
