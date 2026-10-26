<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * SECURITY AU-11–AU-12's credential links (DEP-08; D-SEC-05), IAM's own broker — never the framework's password
 * broker, token table or path-token URL: a token of 256 random bits kept only as a keyed hash, bound to one account
 * and one purpose, single-use with atomic consumption, expiring after 24 hours (ONBOARDING), 60 minutes (RESET) or
 * 30 minutes (OWNER_RECOVERY), voided on reissue and on any password change. Consuming a link sets the password in
 * one transaction with its business audit event — the account as actor under SYSTEM, or no actor with the link
 * named for OWNER_RECOVERY (RG-11; CI-09) —, then ends the account's sessions and records the security event after
 * the commit (LG-01); a link never signs anyone in (AU-11). Issuing is TASK-024's and TASK-020's command, its step-up
 * and audit theirs; `issue` is the store's half they call.
 */
final class CredentialLinks
{
    public const LIFETIMES = ['ONBOARDING' => 24 * 60, 'RESET' => 60, 'OWNER_RECOVERY' => 30];

    public function __construct(
        private readonly SessionTerminator $sessions,
        private readonly SecurityEventRecorder $events,
        private readonly AuditRecorder $audit,
    ) {}

    /** A new link's token, shown once; the account's open link of the same purpose is revoked first (AU-11). */
    public function issue(User $account, string $purpose, ?int $issuedBy): string
    {
        if (! isset(self::LIFETIMES[$purpose])) {
            throw new InvalidArgumentException("{$purpose} is not a credential-link purpose (AU-11).");
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($account, $purpose, $issuedBy, $token, $now) {
            DB::table('credential_tokens')->where('user_id', $account->id)->where('purpose', $purpose)
                ->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => $now]);
            DB::table('credential_tokens')->insert([
                'user_id' => $account->id,
                'purpose' => $purpose,
                'issued_by' => $purpose === 'OWNER_RECOVERY' ? null : $issuedBy,
                'token_hash' => self::hash($token),
                'issued_at' => $now,
                'expires_at' => $now->addMinutes(self::LIFETIMES[$purpose]),
            ]);
        });

        return $token;
    }

    /**
     * Sets the password from a link, or answers null for an unknown, expired, used or revoked one alike (AU-12).
     * The password passed the profile already — its range check outside this transaction (H6-15).
     */
    public function consume(string $token, string $password, ?string $ipAddress, ?string $userAgent): ?User
    {
        $used = DB::transaction(function () use ($token, $password) {
            $link = DB::table('credential_tokens')->where('token_hash', self::hash($token))
                ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->lockForUpdate()->first(['id', 'user_id', 'purpose']);

            if ($link === null) {
                return null;
            }

            $account = User::query()->lockForUpdate()->find($link->user_id);

            if (! $account instanceof User || ! $account->is_active) {
                return null;
            }

            $now = now();
            DB::table('credential_tokens')->where('id', $link->id)->update(['used_at' => $now]);
            // Any password change voids every other open link of the account (AU-11).
            DB::table('credential_tokens')->where('user_id', $account->id)->where('id', '!=', $link->id)
                ->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => $now]);
            $account->forceFill(['password' => Hash::make($password)])->save();

            $recovery = $link->purpose === 'OWNER_RECOVERY';
            $this->audit->record(
                action: 'IAM.CredentialLinkConsumed',
                authorityClass: AuthorityClass::System,
                actorUserId: $recovery ? null : $account->id,
                companyId: null,
                entityType: 'user',
                entityId: $account->id,
                entityPublicId: $account->public_id,
                metadata: ['purpose' => $link->purpose, 'credential_link' => $link->id],
            );

            return [$account, is_string($link->purpose) ? $link->purpose : ''];
        });

        if ($used === null) {
            $this->events->record(SecurityEventKind::CredentialLinkRejected, routeName: 'password.set', ipAddress: $ipAddress, userAgent: $userAgent);

            return null;
        }

        [$account, $purpose] = $used;
        $this->sessions->endAll($account->id);
        $kind = $purpose === 'ONBOARDING' ? SecurityEventKind::AccountSetupCompleted : SecurityEventKind::PasswordResetCompleted;
        $this->events->record($kind, userId: $account->id, reasonCode: $purpose, routeName: 'password.set', ipAddress: $ipAddress, userAgent: $userAgent);

        return $account;
    }

    /** The link's account for AU-03's personal-text rule; none for an unknown link, whose answer comes after. */
    public function accountFor(string $token): ?User
    {
        $userId = DB::table('credential_tokens')->where('token_hash', self::hash($token))->value('user_id');

        return is_numeric($userId) ? User::query()->find((int) $userId) : null;
    }

    /** The keyed hash a token is kept and limited by — never the raw token (AU-11, AU-12). */
    public static function hash(string $token): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $token, is_string($key) ? $key : '');
    }
}
