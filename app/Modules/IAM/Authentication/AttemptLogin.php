<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Normalizer;
use RuntimeException;

/**
 * ROUTE-101 `IAM AttemptLogin`, the password step of SECURITY AU-04: one answer, status and timing for an unknown
 * account, a wrong password and an inactive account (MSG-19) — no account matching, the password is still verified
 * against a dummy hash of the configured driver and parameters —; each failure counted by AU-05's limiter and
 * recorded with only a keyed hash of the identifier (LG-03). The verification, and a rehash's new hash, run within
 * a slot of H6-12. A correct password for an active account rehashes a stale hash with RV-10's conditional update
 * (AU-02) and hands the account to RouteSecondFactor — its challenge, its enrolment-only session or its login,
 * never remembered (AU-08; AU-16, AU-18). Every way of establishing a session runs this sequence — the sign-in
 * page and the in-place re-login of IP-11 (H7-19).
 */
final class AttemptLogin
{
    /** The request attribute that carries the account whose password was verified to the next step. */
    public const VERIFIED = 'iam.verified_account';

    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly SecurityEventRecorder $events,
        private readonly VerificationSlots $slots,
    ) {}

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $identifier = self::identifier($request->input('email'));
        $password = self::password($request->input('password'));
        $user = User::query()->whereRaw('lower(email) = ?', [$identifier])->first();
        $verified = $user?->password;

        try {
            [$matches, $rehash] = $this->slots->unauthenticated('login.store', function () use ($password, $verified) {
                $matches = self::verifies($password, $verified);

                $due = $matches && is_string($verified) && config('hashing.rehash_on_login', true) !== false && Hash::needsRehash($verified);

                return [$matches, $due ? Hash::make($password) : null];
            });
        } catch (VerificationRefused) {
            $this->throttle->release($request, $identifier);

            throw EnsureSignInAllowed::throttled(1);
        }

        if (! $matches || $user === null || ! $user->is_active) {
            // The failure itself gets AU-04's answer; a cooldown it starts holds back the next attempt.
            $this->throttle->failed($request, $identifier);
            $this->events->record(SecurityEventKind::LoginFailed, routeName: 'login.store', identifier: $identifier, ipAddress: $request->ip(), userAgent: $request->userAgent());

            throw self::failure();
        }

        if (is_string($rehash)) {
            // RV-10: only while the row still holds the hash this login verified; a newer credential is never overwritten.
            if (User::query()->whereKey($user->id)->where('password', $verified)->update(['password' => $rehash]) !== 1) {
                $this->throttle->release($request, $identifier);

                throw self::failure();
            }

            $user->forceFill(['password' => $rehash])->syncOriginal();
        }

        $this->throttle->succeeded($request, $identifier);
        // The second factor, or the session, follows in RouteSecondFactor (AU-16, AU-18).
        $request->attributes->set(self::VERIFIED, $user);

        return $next($request);
    }

    /** AU-01: the e-mail trimmed and in lower case. */
    public static function identifier(mixed $email): string
    {
        return mb_strtolower(trim(is_string($email) ? $email : ''));
    }

    /** AU-03: a password normalized to NFC before every check, hash and comparison. */
    public static function password(mixed $password): string
    {
        $text = is_string($password) ? $password : '';

        $normalized = Normalizer::normalize($text, Normalizer::FORM_C);

        return is_string($normalized) && $normalized !== '' ? $normalized : $text;
    }

    /**
     * AU-04's dummy hash, generated from the configured driver and parameters (H9-11): a well-formed hash of a
     * random salt and digest, so verifying against it costs what verifying a real one costs — with no hashing of
     * its own to make it, in any request.
     */
    public static function dummyHash(): string
    {
        $driver = config('hashing.driver');
        $salt = rtrim(base64_encode(random_bytes(16)), '=');
        $digest = rtrim(base64_encode(random_bytes(32)), '=');

        if ($driver === 'argon2id' || $driver === 'argon') {
            $memory = self::setting('hashing.argon.memory', PASSWORD_ARGON2_DEFAULT_MEMORY_COST);
            $time = self::setting('hashing.argon.time', PASSWORD_ARGON2_DEFAULT_TIME_COST);
            $threads = self::setting('hashing.argon.threads', PASSWORD_ARGON2_DEFAULT_THREADS);
            $algorithm = $driver === 'argon' ? 'argon2i' : 'argon2id';

            return "\${$algorithm}\$v=19\$m={$memory},t={$time},p={$threads}\$".$salt.'$'.$digest;
        }

        $rounds = self::setting('hashing.bcrypt.rounds', 12);
        $alphabet = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $tail = '';

        for ($i = 0; $i < 53; $i++) {
            $tail .= $alphabet[random_int(0, 63)];
        }

        return sprintf('$2y$%02d$%s', $rounds, $tail);
    }

    private static function failure(): ValidationException
    {
        return ValidationException::withMessages(['email' => __('messages.msg_19')]);
    }

    /** The same work for every attempt: a missing account, or a hash of another algorithm, is verified against the dummy hash (AU-04). */
    private static function verifies(string $password, ?string $hash): bool
    {
        try {
            return Hash::check($password, $hash ?? self::dummyHash()) && $hash !== null;
        } catch (RuntimeException) {
            // A hash of another algorithm fails like a wrong password, after the same work (AU-02).
            Hash::check($password, self::dummyHash());

            return false;
        }
    }

    private static function setting(string $key, int $default): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
