<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Localization\Locales;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * The account's own self-service of SECURITY AU-13 on PG-065 (ROUTE-111–ROUTE-118), each step on its own row only
 * (SELF; PJ-17) under the account row lock (LK-03; H6-14), its business audit event in its transaction — the account
 * the actor, authority SYSTEM — and its security event after the commit, holding no password, code, secret or token
 * (LG-01–LG-03; CI-09). The sensitive steps run behind the step-up (AU-15), the routes' `step-up` middleware, and a
 * step that takes a current code accepts it once (M-39). After a change of the password or the factor the acting
 * session is restamped, so EnforceSessionLimits keeps it while the account's other sessions end (AU-09; RV-10).
 */
final class OwnAccount
{
    public const DONE = 'DONE';

    public const WRONG_CODE = 'WRONG_CODE';

    public const HIGH_RISK = 'HIGH_RISK';

    public const NO_TOTP = 'NO_TOTP';

    public function __construct(
        private readonly TwoFactorSteps $steps,
        private readonly GoogleIdentities $identities,
        private readonly SessionTerminator $terminator,
        private readonly EffectiveCapabilities $capabilities,
        private readonly AuditRecorder $audit,
        private readonly SecurityEventRecorder $events,
    ) {}

    /** ROUTE-111's rule: the account's current password (AU-13). */
    public static function currentPassword(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            if (! is_string($value) || ! Hash::check($value, (string) self::account($request)->getAuthPassword())) {
                $fail(__('account.password.current_wrong'));
            }
        };
    }

    /** ROUTE-111's rule: AU-03's profile for this account. */
    public static function passwordProfile(Request $request): PasswordProfile
    {
        return new PasswordProfile(self::account($request));
    }

    /** ROUTE-111: the current password checked by the caller's validation; the other sessions end (AU-13; RV-10). */
    public function changePassword(Request $request, #[\SensitiveParameter] string $password): void
    {
        $user = self::account($request);
        $hash = Hash::make($password);

        DB::transaction(function () use ($user, $hash): void {
            $row = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = $row->getRawOriginal('password');
            $row->forceFill(['password' => $hash])->save();

            $this->audit->record(
                action: 'IAM.PasswordChanged',
                authorityClass: AuthorityClass::System,
                actorUserId: $row->id,
                companyId: null,
                entityType: 'user',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                // The password's place in the before/after, its values redacted (RG-11; LG-03).
                change: AuditChange::updated(['password' => $before], ['password' => $hash]),
            );
        });

        $this->restamp($request, $user->id, regenerate: true);
        $this->events->record(SecurityEventKind::PasswordChanged, userId: $user->id, routeName: 'account.password', ipAddress: $request->ip(), userAgent: $request->userAgent());
    }

    /**
     * ROUTE-112's first step: the enrolment's QR code and key for this session's flash data; a replacement first
     * proves the device being replaced by a current code (AU-16; AU-20 a). Answers the enrolment, or WRONG_CODE.
     *
     * @return array{qr: list<string>, key: string, replacing: bool}|string
     *
     * @throws SecondFactorRefused
     */
    public function startEnrolment(Request $request, ?string $currentCode): array|string
    {
        $user = self::account($request);
        $replacing = $user->getAttribute('two_factor_confirmed_at') !== null;

        if ($replacing && $this->steps->sessionCode($request, $user, (string) $currentCode) !== TwoFactorSteps::ACCEPTED) {
            return self::WRONG_CODE;
        }

        $enrolment = $this->steps->enrolment($request);
        $held = $request->session()->get(TwoFactorSteps::ENROLMENT);

        if ($replacing && is_array($held)) {
            $request->session()->put(TwoFactorSteps::REPLACEMENT_PROVED, $held['expires_at'] ?? null);
        }

        return $enrolment;
    }

    /**
     * ROUTE-113: the codes shown in place, never cached — after a current code (AU-19).
     *
     * @return list<string>|string
     *
     * @throws SecondFactorRefused
     */
    public function viewRecoveryCodes(Request $request, string $code): array|string
    {
        $user = self::account($request);
        if ($user->getAttribute('two_factor_confirmed_at') === null) {
            return self::NO_TOTP;
        }

        if ($this->steps->sessionCode($request, $user, $code) !== TwoFactorSteps::ACCEPTED) {
            return self::WRONG_CODE;
        }

        $this->events->record(SecurityEventKind::RecoveryCodesViewed, userId: $user->id, routeName: 'account.recovery-codes', ipAddress: $request->ip(), userAgent: $request->userAgent());
        $this->alertIfHighRisk($user, 'The recovery codes were viewed (AU-19; LG-07).');

        return TwoFactorSteps::recoveryCodesOf(User::query()->findOrFail($user->id));
    }

    /**
     * ROUTE-114: eight new codes shown once, the old ones void (AU-19).
     *
     * @return list<string>|string
     *
     * @throws SecondFactorRefused
     */
    public function regenerateRecoveryCodes(Request $request, string $code): array|string
    {
        $user = self::account($request);
        if ($user->getAttribute('two_factor_confirmed_at') === null) {
            return self::NO_TOTP;
        }

        if ($this->steps->sessionCode($request, $user, $code) !== TwoFactorSteps::ACCEPTED) {
            return self::WRONG_CODE;
        }

        $codes = app(Totp::class)->newRecoveryCodes();

        DB::transaction(function () use ($user, $codes): void {
            $row = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = $row->getRawOriginal('two_factor_recovery_codes');
            $after = Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR));
            DB::table('users')->where('id', $row->id)->update(['two_factor_recovery_codes' => $after]);

            $this->audit->record(
                action: 'IAM.RecoveryCodesRegenerated',
                authorityClass: AuthorityClass::System,
                actorUserId: $row->id,
                companyId: null,
                entityType: 'user',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                change: AuditChange::updated(['two_factor_recovery_codes' => $before], ['two_factor_recovery_codes' => $after]),
            );
        });

        $this->events->record(SecurityEventKind::RecoveryCodesRegenerated, userId: $user->id, routeName: 'account.recovery-codes.regenerate', ipAddress: $request->ip(), userAgent: $request->userAgent());
        $this->alertIfHighRisk($user, 'The recovery codes were regenerated (AU-19; LG-07).');

        return $codes;
    }

    /**
     * ROUTE-115: TOTP off, only while the account is not high-risk — the four fields cleared together and the Google
     * identity unlinked with TOTP_DISABLED (AU-18; AU-20 f; AU-24; C-62).
     *
     * @throws SecondFactorRefused
     */
    public function disableTotp(Request $request, string $code): string
    {
        $user = self::account($request);
        if ($this->capabilities->requiresTotp($user->id)) {
            return self::HIGH_RISK;
        }

        if ($user->getAttribute('two_factor_confirmed_at') === null) {
            return self::NO_TOTP;
        }

        if ($this->steps->sessionCode($request, $user, $code) !== TwoFactorSteps::ACCEPTED) {
            return self::WRONG_CODE;
        }

        $done = DB::transaction(function () use ($user): bool {
            $row = User::query()->lockForUpdate()->findOrFail($user->id);

            // Re-read under the lock: a grant made meanwhile makes the account high-risk (AU-18).
            if ($this->capabilities->requiresTotp($row->id)) {
                return false;
            }

            $before = ['two_factor_secret' => $row->getRawOriginal('two_factor_secret'), 'two_factor_recovery_codes' => $row->getRawOriginal('two_factor_recovery_codes')];
            DB::table('users')->where('id', $row->id)->update(CredentialLinks::CLEARED_TOTP);

            $this->audit->record(
                action: 'IAM.TotpDisabled',
                authorityClass: AuthorityClass::System,
                actorUserId: $row->id,
                companyId: null,
                entityType: 'user',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                change: AuditChange::updated($before, ['two_factor_secret' => null, 'two_factor_recovery_codes' => null]),
            );
            $this->identities->unlink($row, 'TOTP_DISABLED', $row->id);

            return true;
        });

        if (! $done) {
            return self::HIGH_RISK;
        }

        $this->restamp($request, $user->id);
        $this->events->record(SecurityEventKind::TotpDisabled, userId: $user->id, routeName: 'account.totp.disable', ipAddress: $request->ip(), userAgent: $request->userAgent());

        return self::DONE;
    }

    /**
     * ROUTE-116: the holder's confirming request after Google's callback kept the verified identity for this account
     * (AU-23): a current code, then the link under the account row lock. Answers GoogleIdentities' outcome, WRONG_CODE,
     * or NO_TOTP when no verified identity is waiting.
     *
     * @throws SecondFactorRefused
     */
    public function linkGoogle(Request $request, string $code): string
    {
        $user = self::account($request);
        $pending = GoogleSignIn::pendingLink($request, $user);

        if ($pending === null) {
            return self::NO_TOTP;
        }

        if ($this->steps->sessionCode($request, $user, $code) !== TwoFactorSteps::ACCEPTED) {
            return self::WRONG_CODE;
        }

        $request->session()->forget(GoogleSignIn::LINK);

        return $this->identities->link($user, $pending['sub'], $pending['email']);
    }

    /** ROUTE-117: the holder's own unlink, its other sessions ending (AU-24 SELF). */
    public function unlinkGoogle(Request $request): bool
    {
        $user = self::account($request);

        return $this->identities->unlink($user, 'SELF', $user->id, keepSession: $request->session()->getId());
    }

    /** ROUTE-118: `id`, `en` or the default (NULL), kept in `users.locale` and applied at once (RB-10; `DIR-077` CA-08). */
    public function setLocale(Request $request, ?string $locale): void
    {
        $user = self::account($request);
        DB::transaction(function () use ($user, $locale): void {
            $row = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = $row->getAttribute('locale');
            DB::table('users')->where('id', $row->id)->update(['locale' => $locale]);

            $this->audit->record(
                action: 'IAM.LocaleChanged',
                authorityClass: AuthorityClass::System,
                actorUserId: $row->id,
                companyId: null,
                entityType: 'user',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                change: AuditChange::updated(['locale' => $before], ['locale' => $locale]),
            );
        });

        $request->session()->put(Locales::SESSION_KEY, $locale ?? Locales::DEFAULT);
    }

    /** The acting session kept after the account's password or factor changed: its digests read again (AU-09; RV-10). */
    private function restamp(Request $request, int $userId, bool $regenerate = false): void
    {
        $fresh = User::query()->findOrFail($userId);

        if ($regenerate) {
            $request->session()->regenerate(true);
            $this->terminator->endAll($userId, $request->session()->getId());
        }

        $request->session()->put(EnforceSessionLimits::DIGEST, ['user' => $fresh->id, 'digest' => EnforceSessionLimits::digest($fresh)]);
        $request->session()->put(EnforceSessionLimits::FACTOR, ['user' => $fresh->id, 'digest' => EnforceSessionLimits::factorDigest($fresh)]);
    }

    private static function account(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new \LogicException('A self-service step runs in a signed-in session (AU-13).');
    }

    private function alertIfHighRisk(User $user, string $message): void
    {
        if ($this->capabilities->requiresTotp($user->id)) {
            Log::alert($message, ['user_id' => $user->id]);
        }
    }
}
