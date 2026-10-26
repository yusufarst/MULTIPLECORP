<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\DeclareAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireCapability;
use App\Http\Middleware\RequireStepUp;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SessionDeadline;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StartSessionUnlessBackground;
use App\Http\Responses\SystemAnswer;
use App\Modules\IAM\Authentication\EnforceSessionLimits;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // The local stack's fixture pages for the browser suite (TEST_STRATEGY §7): never in testing, staging or
        // production, where only ROUTE_CONTRACTS' routes exist. They keep no session — nothing is stored before
        // sign-in (INT-001) — so they need no database, which the browser job's stack does not migrate.
        then: function (): void {
            if (app()->environment('local')) {
                Route::group([], base_path('routes/fixtures.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global, so that error pages carry the policy too (SECURITY WS-08).
        $middleware->prepend(SecurityHeaders::class);

        // First of all, so that every later log line of the request carries it (ARCHITECTURE §17).
        $middleware->prepend(AssignCorrelationId::class);

        // The session's limits first — the absolute 12 hours, an inactive account and RV-10's password digest, which
        // ends a session whose account's password changed (AU-07, AU-09) —; then the interface language
        // before the shared props that carry it (LOCALIZATION §2; WS-07).
        $middleware->web(append: [
            EnforceSessionLimits::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            SessionDeadline::class,
        ]);

        // A guest's document request goes to sign-in, PG-001 (TASK-017); an Inertia or JSON one answers 401 instead.
        $middleware->redirectGuestsTo('/masuk');
        // A signed-in account opening a sign-in page goes to its landing page (NAV-001).
        $middleware->redirectUsersTo('/');

        // A background request reads the session and never writes it, so it extends no session (RV-06; RB-05).
        $middleware->web(replace: [StartSession::class => StartSessionUnlessBackground::class]);

        // Each route's declaration of what admits it, after `auth` (SECURITY EN-01, EN-03; the route audit, SM-02).
        $middleware->alias([
            'capability' => RequireCapability::class,
            'access' => DeclareAccess::class,
            'step-up' => RequireStepUp::class,
        ]);

        // The capability is checked before any route binding reads a record, so a missing and an existing
        // record answer an account without it alike (AZ-11).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: RequireCapability::class);

        // The session's limits are judged before the authentication middleware admits the request, so a session past
        // its absolute limit, of an inactive account or of a changed password is a guest's at once (AU-07, AU-09).
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: EnforceSessionLimits::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // One cron entry runs the scheduler each minute (TASK-072's); every task is overlap-protected on the store
        // with an expiry longer than its longest run, so with the store down it does not run and the next minute
        // tries again (CONCURRENCY_IDEMPOTENCY §15; HO-20). A daily task that misses its minute that way is not caught
        // up here: P9 alerts on a day without a successful run (HO-25).
        $schedule->command('renditions:sweep')->everyMinute()->withoutOverlapping(10);
        $schedule->command('owed-work:sweep')->everyMinute()->withoutOverlapping(10);
        $schedule->command('guards:reconcile')->dailyAt('02:00')->timezone('Asia/Jakarta')->withoutOverlapping(180);
        $schedule->command('command-log:purge')->daily()->withoutOverlapping(60);
        // The failed jobs' retention, below the command log's 30 days less the queue's retry window (CI-07; HO-27).
        $schedule->command('queue:prune-failed', ['--hours' => 336])->daily()->withoutOverlapping(30);
        // The credential-link store's own purge (SECURITY AU-11; H9-04).
        $schedule->command('credential-links:purge')->daily()->withoutOverlapping(30);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // One answer for a missing and an out-of-scope record, naming neither (AZ-11; CS-04; WS-10).
        $exceptions->map(ModelNotFoundException::class, fn () => new NotFoundHttpException);

        // No secret and no credential-link token is flashed back after a validation error (AU-12; WS-10).
        $exceptions->dontFlash(['token', 'password', 'password_confirmation', 'current_password']);

        // UXH-11: an unauthenticated Inertia or JSON request answers 401 — never a redirect —, so the open page
        // signs in again in place (IP-11); PG-005 answers an address the account cannot open (IP-21; TASK-016).
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => SystemAnswer::unauthenticated($exception, $request));
        $exceptions->respond(fn (Response $response, Throwable $exception, Request $request): Response => SystemAnswer::respond($response, $exception, $request));
    })->create();
