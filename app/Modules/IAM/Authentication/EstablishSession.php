<?php

namespace App\Modules\IAM\Authentication;

use App\Http\Middleware\RequireStepUp;
use App\Http\Middleware\SessionDeadline;
use App\Modules\IAM\Models\User;
use App\Support\Localization\Locales;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Closure;
use Illuminate\Http\Request;

/**
 * The last step of ROUTE-101, after Fortify regenerated the session identifier (AU-06; H8-17), and of the
 * second-factor steps that establish a session (ROUTE-104, ROUTE-105): the sign-in time the absolute limit
 * counts from (AU-07; UXH-11), RV-10's password digest and AU-06's digest of the enrolment, how the session was established — PASSWORD or TOTP
 * (AU-18) —, the account's language read into the session (AU-13; RB-10), and LOGIN_SUCCEEDED recorded (AU-04;
 * LG-02). An enrolment-only session counts the password step that opened it as the password confirmation for
 * 15 minutes (AU-15, AU-18; H7-15).
 */
final class EstablishSession
{
    public const PASSWORD = 'PASSWORD';

    public const TOTP = 'TOTP';

    public function __construct(private readonly SecurityEventRecorder $events) {}

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->stamp($request, $user, self::PASSWORD);
        }

        return $next($request);
    }

    /** Stamps the session the account was just signed in to, its identifier already regenerated. */
    public function stamp(Request $request, User $user, string $method, ?string $detail = null): void
    {
        $session = $request->session();
        $session->put(SessionDeadline::SIGNED_IN_AT, now()->getTimestamp());
        $session->put(EnforceSessionLimits::DIGEST, ['user' => $user->id, 'digest' => EnforceSessionLimits::digest($user)]);
        $session->put(EnforceSessionLimits::FACTOR, ['user' => $user->id, 'digest' => EnforceSessionLimits::factorDigest($user)]);
        $session->put(TwoFactorChallenge::METHOD, $method);
        $session->put(Locales::SESSION_KEY, Locales::supported($user->locale) ?? Locales::DEFAULT);

        if (TwoFactorChallenge::enrolmentOnly($request)) {
            $session->put(RequireStepUp::CONFIRMED_AT, now()->getTimestamp());
        }

        $this->events->record(SecurityEventKind::LoginSucceeded, userId: $user->id, routeName: $request->route()?->getName() ?? 'login.store', ipAddress: $request->ip(), userAgent: $request->userAgent(), details: ['session_method' => $detail ?? strtolower($method)]);
    }
}
