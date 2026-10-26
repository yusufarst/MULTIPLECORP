<?php

namespace App\Modules\IAM\Authentication;

use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;

/**
 * IAM's authentication boundary (ARCHITECTURE §8; D-SEC-17; TASK-017): Fortify behind it, its own routes ignored —
 * IAM declares ROUTE-101 and ROUTE-108 itself, so Fortify registers nothing the contracts do not list (SM-22) —,
 * the sign-in pipeline of AU-04 and AU-05, the answers of AU-06, AU-03's breached-password range check, the
 * credential page's limiter of AU-12 and LOGOUT's security event.
 */
final class AuthenticationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
        $this->app->singleton(LoginResponse::class, SignInResponses::class);
        $this->app->singleton(LogoutResponse::class, SignOutResponse::class);
        $this->app->bind(LoginRequest::class, SignInRequest::class);
    }

    public function boot(): void
    {
        // In boot: the framework's validation provider is deferred and would replace a binding made in register.
        $this->app->bind(UncompromisedVerifier::class, BreachedPasswordCheck::class);

        Fortify::authenticateThrough(fn () => [
            EnsureSignInAllowed::class,
            AttemptLogin::class,
            RouteSecondFactor::class,
            PrepareAuthenticatedSession::class,
            EstablishSession::class,
        ]);

        // AU-12: consumption limited per address and per token hash — a raw token is never a limiter key —, its
        // answer PG-003's MSG-20 rather than a bare 429.
        $throttled = fn (Request $request, array $headers): RedirectResponse => CredentialPageHeaders::throttled(is_numeric($headers['Retry-After'] ?? null) ? (int) $headers['Retry-After'] : 60);
        RateLimiter::for('credential-link', fn (Request $request): array => [
            Limit::perMinute(10)->by('credential-link:address:'.$request->ip())->response($throttled),
            Limit::perMinute(5)->by('credential-link:token:'.CredentialLinks::hash(is_string($request->input('token')) ? $request->input('token') : ''))->response($throttled),
        ]);

        // AU-05: the Google redirect and callback throttled per client address, answered on PG-001 with MSG-20.
        RateLimiter::for('google', fn (Request $request): Limit => Limit::perMinute(10)->by('google:'.LoginThrottle::addressKey($request->ip()))
            ->response(fn (Request $request, array $headers): RedirectResponse => redirect('/masuk')->withErrors(['email' => trans_choice('messages.msg_20', 1, ['count' => 1])])));

        // H6-15: a pending challenge's session row carries its account (FactorAwareSessionHandler).
        Session::extend('database', fn (Container $app): FactorAwareSessionHandler => new FactorAwareSessionHandler(
            DB::connection(is_string(config('session.connection')) ? config('session.connection') : null),
            config()->string('session.table', 'sessions'),
            config()->integer('session.lifetime', 120),
            $app,
        ));

        Event::listen(Logout::class, function (Logout $event): void {
            if (request()->routeIs('logout')) {
                $id = $event->user->getAuthIdentifier();
                app(SecurityEventRecorder::class)->record(SecurityEventKind::Logout, userId: is_numeric($id) ? (int) $id : null, routeName: 'logout', ipAddress: request()->ip(), userAgent: request()->userAgent());
            }
        });
    }
}
