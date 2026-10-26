<?php

namespace App\Modules\IAM\Authentication;

use App\Http\Middleware\SessionDeadline;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SECURITY AU-07's absolute limit and AU-09's inactive account, on every request of the web group: a session
 * past 12 hours from its sign-in, or of an account no longer active, is ended before the route runs — signed
 * out, its row deleted by the invalidation and its forgery token renewed —, so the request goes on as a guest's
 * and meets the authentication middleware's answer (UXH-11). The idle limit is the session store's own
 * lifetime, which only a user-initiated request extends (H7-13; StartSessionUnlessBackground). AU-18 on the same
 * guard: a high-risk account's session that was not established with TOTP is ended (TOTP_REQUIRED) unless it is
 * the enrolment-only session, which reaches PG-004, the password confirmation and logout and nothing else. Both
 * apply to a session signed in through the session guard — the only way a request is authenticated here.
 */
final class EnforceSessionLimits
{
    /** RV-10's password digest of the session's account, so a password changed elsewhere ends this session. */
    public const DIGEST = 'auth.password_digest';

    /** The routes AU-18's enrolment-only session reaches. */
    public const ENROLMENT_ROUTES = ['two-factor.enrolment', 'two-factor.enrolment.confirm', 'password.confirm', 'logout'];

    public function __construct(
        private readonly SecurityEventRecorder $events,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /** A keyed digest of the account's stored hash — never the hash itself in the session. */
    public static function digest(User $user): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $user->getAuthPassword(), is_string($key) ? $key : '');
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->hasSession() ? Auth::guard('web')->user() : null;

        if ($user instanceof User) {
            $signedIn = $request->session()->get(SessionDeadline::SIGNED_IN_AT);

            if (! is_numeric($signedIn)) {
                // A session that missed its sign-in time is never unbounded: the limit counts from now (AU-07).
                $signedIn = now()->getTimestamp();
                $request->session()->put(SessionDeadline::SIGNED_IN_AT, $signedIn);
            }

            $expired = now()->getTimestamp() >= (int) $signedIn + SessionDeadline::ABSOLUTE_HOURS * 3600;
            $digest = self::digest($user);
            $stored = $request->session()->get(self::DIGEST);
            $sameAccount = is_array($stored) && ($stored['user'] ?? null) === $user->id;
            $passwordChanged = $sameAccount && ! hash_equals(is_string($stored['digest'] ?? null) ? $stored['digest'] : '', $digest);

            // The account as stored: a session's user is read from the database, so `false` is a deactivation.
            $reason = match (true) {
                $expired => 'ABSOLUTE_LIMIT',
                $user->getAttribute('is_active') === false => 'INACTIVE_ACCOUNT',
                $passwordChanged => 'PASSWORD_CHANGED',
                $this->needsTotp($request, $user) => 'TOTP_REQUIRED',
                default => null,
            };

            if ($reason !== null) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $this->events->record(SecurityEventKind::SessionInvalidated, userId: $user->id, reasonCode: $reason, ipAddress: $request->ip(), userAgent: $request->userAgent());
            } else {
                if (! $sameAccount) {
                    $request->session()->put(self::DIGEST, ['user' => $user->id, 'digest' => $digest]);
                }

                if (TwoFactorChallenge::enrolmentOnly($request) && ! in_array($request->route()?->getName(), self::ENROLMENT_ROUTES, true)) {
                    return $request->isMethod('GET') && ! $request->expectsJson()
                        ? redirect(SignInResponses::ENROLMENT)
                        : response()->json(['message' => __('messages.msg_10')], Response::HTTP_FORBIDDEN);
                }
            }
        }

        return $next($request);
    }

    /** A high-risk account's session signed in without TOTP, other than the enrolment-only session (AU-18). */
    private function needsTotp(Request $request, User $user): bool
    {
        $session = $request->session();

        return $session->has(Auth::guard('web')->getName())
            && $session->get(TwoFactorChallenge::METHOD) !== EstablishSession::TOTP
            && ! TwoFactorChallenge::enrolmentOnly($request)
            && $this->capabilities->requiresTotp($user->id);
    }
}
