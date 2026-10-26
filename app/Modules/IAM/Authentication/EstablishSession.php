<?php

namespace App\Modules\IAM\Authentication;

use App\Http\Middleware\SessionDeadline;
use App\Modules\IAM\Models\User;
use App\Support\Localization\Locales;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Closure;
use Illuminate\Http\Request;

/**
 * The last step of ROUTE-101, after Fortify regenerated the session identifier (AU-06; H8-17): the sign-in time
 * the absolute limit counts from (AU-07; UXH-11), the account's language read into the session (AU-13; RB-10),
 * and LOGIN_SUCCEEDED recorded (AU-04; LG-02).
 */
final class EstablishSession
{
    public function __construct(private readonly SecurityEventRecorder $events) {}

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user instanceof User) {
            $request->session()->put(SessionDeadline::SIGNED_IN_AT, now()->getTimestamp());
            $request->session()->put(EnforceSessionLimits::DIGEST, ['user' => $user->id, 'digest' => EnforceSessionLimits::digest($user)]);
            $request->session()->put(Locales::SESSION_KEY, Locales::supported($user->locale) ?? Locales::DEFAULT);
            $this->events->record(SecurityEventKind::LoginSucceeded, userId: $user->id, routeName: 'login.store', ipAddress: $request->ip(), userAgent: $request->userAgent(), details: ['session_method' => 'password']);
        }

        return $next($request);
    }
}
