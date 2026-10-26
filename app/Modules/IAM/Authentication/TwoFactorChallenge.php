<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use Illuminate\Http\Request;

/**
 * SECURITY AU-16's pending challenge and the session's factor state. After a correct password an account with
 * active TOTP gets a challenge in its session — the account, the purpose (PASSWORD, or GOOGLE with TASK-019), a
 * keyed digest of its credential and factor state and an expiry of 5 minutes — and no login; the session row
 * carries the account's id meanwhile (FactorAwareSessionHandler), so deleting the account's sessions voids the
 * challenge. The challenge is void once it expires or that state changes, at a breach of the second-factor limit,
 * and when PG-001 is requested again (NAV-002; OQ-14). The session records how it was established — PASSWORD or
 * TOTP — and, for AU-18's enrolment-only session, that it is one and whether a recovery code opened it (AU-20 b).
 */
final class TwoFactorChallenge
{
    public const KEY = 'auth.challenge';

    /** How the session was established: PASSWORD or TOTP (AU-06, AU-18). */
    public const METHOD = 'auth.session_method';

    /** AU-18's enrolment-only session. */
    public const ENROLMENT_ONLY = 'auth.enrolment_only';

    /** AU-20 b's recovery mark, set by the recovery-code transaction in that session alone (RV-13). */
    public const RECOVERY_MARK = 'auth.recovery_mark';

    public const PURPOSE_PASSWORD = 'PASSWORD';

    public const PURPOSE_GOOGLE = 'GOOGLE';

    public const LIFETIME_SECONDS = 300;

    /** Opens the challenge in a session of its own, so no identifier carries across the steps (TM-03; AU-06). */
    public static function begin(Request $request, User $user, string $purpose): void
    {
        $request->session()->regenerate(true);
        $request->session()->put(self::KEY, [
            'user' => $user->id,
            'purpose' => $purpose,
            'digest' => self::digest($user),
            'email' => $user->email,
            'expires_at' => now()->getTimestamp() + self::LIFETIME_SECONDS,
        ]);
    }

    /**
     * The pending challenge while it may still complete a sign-in, else null.
     *
     * @return array{user: int, purpose: string, digest: string, email: string, expires_at: int}|null
     */
    public static function current(Request $request): ?array
    {
        $challenge = $request->hasSession() ? $request->session()->get(self::KEY) : null;

        if (! is_array($challenge) || ! is_int($challenge['user'] ?? null) || ! is_string($challenge['digest'] ?? null)
            || ! is_int($challenge['expires_at'] ?? null) || ! is_string($challenge['purpose'] ?? null) || ! is_string($challenge['email'] ?? null)) {
            return null;
        }

        return $challenge['expires_at'] > now()->getTimestamp() ? $challenge : null;
    }

    /** The e-mail of a challenge, expired or not, so PG-001 keeps it (NAV-002). */
    public static function email(Request $request): ?string
    {
        $challenge = $request->hasSession() ? $request->session()->get(self::KEY) : null;

        return is_array($challenge) && is_string($challenge['email'] ?? null) ? $challenge['email'] : null;
    }

    public static function void(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::KEY);
        }
    }

    /**
     * A keyed digest of the account's credential and factor state — its password hash, its confirmation time
     * and its stored recovery codes —, so a change of any of them voids the challenge (AU-16).
     */
    public static function digest(User $user): string
    {
        $key = config('app.key');
        $confirmed = $user->getAttribute('two_factor_confirmed_at');
        $state = implode('|', [
            $user->getAuthPassword(),
            $confirmed instanceof \DateTimeInterface ? $confirmed->format('U.u') : '',
            is_string($codes = $user->getRawOriginal('two_factor_recovery_codes')) ? $codes : '',
        ]);

        return hash_hmac('sha256', $state, is_string($key) ? $key : '');
    }

    /** Whether the session is AU-18's enrolment-only session. */
    public static function enrolmentOnly(Request $request): bool
    {
        return $request->hasSession() && $request->session()->get(self::ENROLMENT_ONLY) === true;
    }
}
