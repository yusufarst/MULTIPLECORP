<?php

namespace App\Modules\IAM\Authentication;

use App\Modules\IAM\Models\User;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Normalizer;
use RuntimeException;

/**
 * ROUTE-101 `IAM AttemptLogin`, the password step of SECURITY AU-04: one answer, status and timing for an unknown
 * account, a wrong password and an inactive account (MSG-19) — no account matching, the password is still verified
 * against a dummy hash of the configured driver and parameters —; each failure counted by AU-05's limiter and
 * recorded with only a keyed hash of the identifier (LG-03). A correct password for an active account rehashes a
 * stale hash (AU-02) and signs the account in, never remembered (AU-08). Every way of establishing a session runs
 * this sequence — the sign-in page and the in-place re-login of IP-11 (H7-19); the second factor and the
 * enrolment-only session follow this step in TASK-018 (AU-16, AU-18).
 */
final class AttemptLogin
{
    private static ?string $dummyHash = null;

    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly LoginThrottle $throttle,
        private readonly SecurityEventRecorder $events,
    ) {}

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $identifier = self::identifier($request->input('email'));
        $password = self::password($request->input('password'));
        $user = User::query()->whereRaw('lower(email) = ?', [$identifier])->first();

        if (! self::verifies($password, $user?->password) || $user === null || ! $user->is_active) {
            // The failure itself gets AU-04's answer; a cooldown it starts holds back the next attempt.
            $this->throttle->failed($request, $identifier);
            $this->events->record(SecurityEventKind::LoginFailed, routeName: 'login.store', identifier: $identifier, ipAddress: $request->ip(), userAgent: $request->userAgent());

            throw ValidationException::withMessages(['email' => __('messages.msg_19')]);
        }

        Auth::getProvider()->rehashPasswordIfRequired($user, ['password' => $password]);
        $this->guard->login($user);
        $this->throttle->succeeded($request, $identifier);

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

    /** The same work for every attempt: a missing account is verified against a dummy hash (AU-04; H9-11). */
    private static function verifies(string $password, ?string $hash): bool
    {
        self::$dummyHash ??= Hash::make(bin2hex(random_bytes(16)));

        try {
            return Hash::check($password, $hash ?? self::$dummyHash) && $hash !== null;
        } catch (RuntimeException) {
            // A hash of another algorithm fails verification like a wrong password (AU-02).
            return false;
        }
    }
}
