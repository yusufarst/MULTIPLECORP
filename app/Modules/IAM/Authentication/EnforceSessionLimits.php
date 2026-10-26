<?php

namespace App\Modules\IAM\Authentication;

use App\Http\Middleware\SessionDeadline;
use App\Modules\IAM\Models\User;
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
 * lifetime, which only a user-initiated request extends (H7-13; StartSessionUnlessBackground).
 */
final class EnforceSessionLimits
{
    /** RV-10's password digest of the session's account, so a password changed elsewhere ends this session. */
    public const DIGEST = 'auth.password_digest';

    public function __construct(private readonly SecurityEventRecorder $events) {}

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
                default => null,
            };

            if ($reason !== null) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $this->events->record(SecurityEventKind::SessionInvalidated, userId: $user->id, reasonCode: $reason, ipAddress: $request->ip(), userAgent: $request->userAgent());
            } elseif (! $sameAccount) {
                $request->session()->put(self::DIGEST, ['user' => $user->id, 'digest' => $digest]);
            }
        }

        return $next($request);
    }
}
