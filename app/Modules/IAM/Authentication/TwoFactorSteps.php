<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\LoginResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The second-factor steps of SECURITY AU-16, AU-18, AU-19 and AU-20 b, IAM's own — never Fortify's routes, its
 * cache-based reuse check or its recovery-code replacement:
 *
 * - ROUTE-104, the challenge's TOTP code (RV-12): the place in AU-05's count first; the code's step found
 *   outside any transaction after `two_factor_last_step`; then, under the account row lock, the account active,
 *   its factor state the challenge's, and one conditional update of the step — so one code passes once, in
 *   sequence and concurrently (M-39) —; the session then established with TOTP under a new identifier.
 * - ROUTE-105, a recovery code after a password only (RV-13): found by constant-time comparison and removed from
 *   the stored set by an update that matches only the set read under the lock; the account's unused links
 *   revoked and its other sessions ended; the session an enrolment-only one with the recovery mark (AU-20 b).
 * - ROUTE-004 and ROUTE-107, the enrolment (H6-15): a new secret held encrypted in the session for 15 minutes,
 *   its QR code and text key shown only then; one correct code confirms it — secret, eight recovery codes, the
 *   time and the code's step written together under the account row lock —, the old secret and codes of a
 *   recovery replaced, the account's other sessions ended and this one restamped as established with TOTP.
 *
 * Each account-changing step writes its business audit event in its transaction, the account as actor under
 * SYSTEM, and its security event after the commit (RG-11; CI-09; LG-01).
 */
final class TwoFactorSteps
{
    /** The enrolment in progress: its secret, encrypted, and when it lapses (AU-16). */
    public const ENROLMENT = 'auth.enrolment';

    public const ENROLMENT_SECONDS = 900;

    public function __construct(
        private readonly Totp $totp,
        private readonly SecondFactorLimit $limit,
        private readonly StatefulGuard $guard,
        private readonly EstablishSession $sessions,
        private readonly SessionTerminator $terminator,
        private readonly EffectiveCapabilities $capabilities,
        private readonly AuditRecorder $audit,
        private readonly SecurityEventRecorder $events,
    ) {}

    /** ROUTE-104: the challenge's TOTP code. */
    public function verifyCode(Request $request, string $code): Response
    {
        $challenge = TwoFactorChallenge::current($request);

        if ($challenge === null) {
            return TwoFactorAnswers::expired($request);
        }

        $user = $this->challenged($challenge);

        if ($user === null) {
            TwoFactorChallenge::void($request);

            return TwoFactorAnswers::mismatch($request);
        }

        try {
            $place = $this->limit->take($user->id, $request);
        } catch (SecondFactorRefused $refused) {
            return TwoFactorAnswers::throttled($request, $refused->seconds);
        }

        $path = $challenge['purpose'] === TwoFactorChallenge::PURPOSE_GOOGLE ? SecondFactorLimit::PATH_GOOGLE : SecondFactorLimit::PATH_PASSWORD;
        $step = $this->totp->stepOf(self::secretOf($user), $code, self::lastStepOf($user));
        $accepted = $step !== null && DB::transaction(function () use ($user, $challenge, $step): bool {
            $row = User::query()->lockForUpdate()->find($user->id);

            if (! $row instanceof User || $row->getAttribute('is_active') !== true || ! self::sameSecret($row, $user)
                || ! hash_equals($challenge['digest'], TwoFactorChallenge::digest($row))) {
                return false;
            }

            return DB::table('users')->where('id', $row->id)->where('two_factor_last_step', '<', $step)->update(['two_factor_last_step' => $step]) === 1;
        });

        if (! $accepted) {
            return $this->failure($request, $place, $path);
        }

        $this->limit->accepted($place);
        TwoFactorChallenge::void($request);
        $this->signIn($request, $user, EstablishSession::TOTP);

        return app(LoginResponse::class)->toResponse($request);
    }

    /** ROUTE-105: a recovery code at a challenge after a password (AU-19; AU-20 b). */
    public function useRecoveryCode(Request $request, string $code): Response
    {
        $challenge = TwoFactorChallenge::current($request);

        if ($challenge === null) {
            return TwoFactorAnswers::expired($request);
        }

        $user = $this->challenged($challenge);

        // After Google a TOTP code only (AU-19; AU-22): a recovery code there is answered like any failing code.
        if ($user === null || $challenge['purpose'] !== TwoFactorChallenge::PURPOSE_PASSWORD) {
            TwoFactorChallenge::void($request);

            return TwoFactorAnswers::mismatch($request, 'recovery_code');
        }

        try {
            $place = $this->limit->take($user->id, $request);
        } catch (SecondFactorRefused $refused) {
            return TwoFactorAnswers::throttled($request, $refused->seconds, 'recovery_code');
        }

        $submitted = trim($code);
        $sessionId = $request->session()->getId();
        $used = $submitted !== '' && DB::transaction(function () use ($user, $challenge, $submitted, $sessionId): bool {
            $row = User::query()->lockForUpdate()->find($user->id);

            if (! $row instanceof User || $row->getAttribute('is_active') !== true || ! hash_equals($challenge['digest'], TwoFactorChallenge::digest($row))) {
                return false;
            }

            $stored = $row->getRawOriginal('two_factor_recovery_codes');
            $codes = is_string($stored) ? self::codesOf($stored) : [];
            $match = null;

            foreach ($codes as $index => $candidate) {
                if (hash_equals($candidate, $submitted)) {
                    $match = $index;
                }
            }

            if ($match === null) {
                return false;
            }

            unset($codes[$match]);
            // An emptied set stays an encrypted empty list, never NULL, so the four fields stay set together (C-62).
            $left = Crypt::encryptString(json_encode(array_values($codes), JSON_THROW_ON_ERROR));

            if (DB::table('users')->where('id', $row->id)->where('two_factor_recovery_codes', $stored)->update(['two_factor_recovery_codes' => $left]) !== 1) {
                return false;
            }

            $this->audit->record(
                action: 'IAM.RecoveryCodeUsed',
                authorityClass: AuthorityClass::System,
                actorUserId: $row->id,
                companyId: null,
                entityType: 'user',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                change: AuditChange::updated(['two_factor_recovery_codes' => $stored], ['two_factor_recovery_codes' => $left]),
                metadata: ['codes_left' => count($codes)],
            );
            // AU-20's every path, as RV-13 orders it: the unused links revoked and the other sessions ended last.
            DB::table('credential_tokens')->where('user_id', $row->id)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            DB::table('sessions')->where('user_id', $row->id)->where('id', '!=', $sessionId)->delete();

            return true;
        });

        if (! $used) {
            return $this->failure($request, $place, SecondFactorLimit::PATH_PASSWORD, 'recovery_code');
        }

        $this->events->record(SecurityEventKind::RecoveryCodeUsed, userId: $user->id, routeName: 'two-factor.recovery', ipAddress: $request->ip(), userAgent: $request->userAgent());
        $this->alertIfHighRisk($user, 'A recovery code was used (AU-19; LG-07).');
        $this->limit->accepted($place);
        TwoFactorChallenge::void($request);
        $request->session()->put(TwoFactorChallenge::ENROLMENT_ONLY, true);
        $request->session()->put(TwoFactorChallenge::RECOVERY_MARK, true);
        $this->signIn($request, $user, EstablishSession::PASSWORD, 'recovery_code');

        return app(LoginResponse::class)->toResponse($request);
    }

    /**
     * ROUTE-004: the enrolment in progress, a new secret when none is held or it lapsed; its QR code and text
     * key, for the page's flash data only.
     *
     * @return array{qr: list<string>, key: string, replacing: bool}
     */
    public function enrolment(Request $request): array
    {
        $user = self::signedIn($request);
        $held = $request->session()->get(self::ENROLMENT);
        $secret = null;

        if (is_array($held) && is_int($held['expires_at'] ?? null) && $held['expires_at'] > now()->getTimestamp() && is_string($held['secret'] ?? null)) {
            try {
                $secret = Crypt::decryptString($held['secret']);
            } catch (Throwable) {
                $secret = null;
            }
        }

        if ($secret === null) {
            $secret = $this->totp->newSecret();
            $request->session()->put(self::ENROLMENT, ['secret' => Crypt::encryptString($secret), 'expires_at' => now()->getTimestamp() + self::ENROLMENT_SECONDS]);
        }

        return [
            'qr' => $this->totp->qrModules($user->email, $secret),
            'key' => trim(chunk_split($secret, 4, ' ')),
            'replacing' => $user->getAttribute('two_factor_confirmed_at') !== null,
        ];
    }

    /**
     * ROUTE-107: confirms the enrolment with one code for the new secret; answers the eight recovery codes, or
     * null for a code that matches nothing, an enrolment lapsed or a state changed meanwhile.
     *
     * @return list<string>|null
     */
    public function confirmEnrolment(Request $request, string $code): ?array
    {
        $user = self::signedIn($request);
        $held = $request->session()->get(self::ENROLMENT);

        if (! is_array($held) || ! is_int($held['expires_at'] ?? null) || $held['expires_at'] <= now()->getTimestamp() || ! is_string($held['secret'] ?? null)) {
            return null;
        }

        $secret = Crypt::decryptString($held['secret']);
        $step = $this->totp->stepOf($secret, $code, null);
        $replacing = $user->getAttribute('two_factor_confirmed_at') !== null;

        // A device is replaced here only by AU-20 b's recovery-code session; any other replacement is path (a).
        if ($step === null || ($replacing && $request->session()->get(TwoFactorChallenge::RECOVERY_MARK) !== true)) {
            return null;
        }

        $codes = $this->totp->newRecoveryCodes();
        $digest = $request->session()->get(EnforceSessionLimits::DIGEST);
        $confirmed = DB::transaction(function () use ($user, $secret, $codes, $step, $digest, $replacing): bool {
            $row = User::query()->lockForUpdate()->find($user->id);

            // RV-12: the account active, its password the session's (RV-10), its factor state the one this session saw.
            if (! $row instanceof User || $row->getAttribute('is_active') !== true || ! is_array($digest)
                || ! hash_equals(is_string($digest['digest'] ?? null) ? $digest['digest'] : '', EnforceSessionLimits::digest($row))
                || ! self::sameSecret($row, $user)) {
                return false;
            }

            $before = ['two_factor_secret' => $row->getRawOriginal('two_factor_secret'), 'two_factor_recovery_codes' => $row->getRawOriginal('two_factor_recovery_codes')];
            $after = [
                'two_factor_secret' => Crypt::encryptString($secret),
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR)),
                'two_factor_confirmed_at' => now(),
                'two_factor_last_step' => $step,
            ];
            DB::table('users')->where('id', $row->id)->update($after);

            $this->audit->record(
                action: $replacing ? 'IAM.TotpReplaced' : 'IAM.TotpEnrolled',
                authorityClass: AuthorityClass::System,
                actorUserId: $row->id,
                companyId: null,
                entityType: 'user',
                entityId: $row->id,
                entityPublicId: $row->public_id,
                change: AuditChange::updated($before, ['two_factor_secret' => $after['two_factor_secret'], 'two_factor_recovery_codes' => $after['two_factor_recovery_codes']]),
            );

            return true;
        });

        if (! $confirmed) {
            return null;
        }

        $this->events->record($replacing ? SecurityEventKind::TotpReplaced : SecurityEventKind::TotpEnrolled, userId: $user->id, routeName: 'two-factor.enrolment.confirm', ipAddress: $request->ip(), userAgent: $request->userAgent());
        $this->alertIfHighRisk($user, $replacing ? 'A TOTP device was replaced (AU-20; LG-07).' : 'A TOTP device was enrolled (AU-18; LG-07).');

        // The enrolment's change ends every other session and restamps this one, under a new identifier (AU-16; AU-06).
        $request->session()->forget([self::ENROLMENT, TwoFactorChallenge::ENROLMENT_ONLY, TwoFactorChallenge::RECOVERY_MARK]);
        $request->session()->regenerate(true);
        $this->terminator->endAll($user->id, $request->session()->getId());
        $fresh = User::query()->findOrFail($user->id);
        $this->guard->setUser($fresh);
        $request->session()->put(TwoFactorChallenge::METHOD, EstablishSession::TOTP);
        $request->session()->put(EnforceSessionLimits::DIGEST, ['user' => $fresh->id, 'digest' => EnforceSessionLimits::digest($fresh)]);

        return $codes;
    }

    private static function signedIn(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new \LogicException('The enrolment runs in a signed-in session (AU-18).');
    }

    /**
     * The account of a challenge still bound to it: active, its factor state unchanged (AU-16).
     *
     * @param  array{user: int, purpose: string, digest: string, email: string, expires_at: int}  $challenge
     */
    private function challenged(array $challenge): ?User
    {
        $user = User::query()->find($challenge['user']);

        return $user instanceof User && $user->getAttribute('is_active') === true && $user->getAttribute('two_factor_confirmed_at') !== null
            && hash_equals($challenge['digest'], TwoFactorChallenge::digest($user)) ? $user : null;
    }

    /** A failing code keeps its place and is recorded; a breach voids the challenge (AU-05). */
    private function failure(Request $request, SecondFactorPlace $place, string $path, string $field = 'code'): Response
    {
        try {
            if ($this->limit->failed($place, $path, $request)) {
                TwoFactorChallenge::void($request);
            }
        } catch (SecondFactorRefused $refused) {
            return TwoFactorAnswers::throttled($request, $refused->seconds, $field);
        }

        return TwoFactorAnswers::mismatch($request, $field);
    }

    private function signIn(Request $request, User $user, string $method, ?string $detail = null): void
    {
        $this->guard->login($user);
        $request->session()->regenerate(true);
        $this->sessions->stamp($request, $user, $method, $detail);
    }

    private function alertIfHighRisk(User $user, string $message): void
    {
        if ($this->capabilities->requiresTotp($user->id)) {
            Log::alert($message, ['user_id' => $user->id]);
        }
    }

    private static function secretOf(User $user): string
    {
        $stored = $user->getRawOriginal('two_factor_secret');

        try {
            return is_string($stored) ? Crypt::decryptString($stored) : '';
        } catch (Throwable) {
            return '';
        }
    }

    private static function lastStepOf(User $user): ?int
    {
        $step = $user->getRawOriginal('two_factor_last_step');

        return is_numeric($step) ? (int) $step : null;
    }

    /** @return list<string> */
    private static function codesOf(string $stored): array
    {
        try {
            $codes = json_decode(Crypt::decryptString($stored), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
    }

    /** The secret a code was checked against is still the stored one: its confirmation time unchanged (AU-16). */
    private static function sameSecret(User $row, User $seen): bool
    {
        return $row->getRawOriginal('two_factor_confirmed_at') === $seen->getRawOriginal('two_factor_confirmed_at');
    }
}
