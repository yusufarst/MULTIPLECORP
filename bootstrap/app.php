<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\DeclareAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireCapability;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
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

        // The interface language before the shared props that carry it (LOCALIZATION §2; WS-07).
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
        ]);

        // Each route's declaration of what admits it, after `auth` (SECURITY EN-01, EN-03; the route audit, SM-02).
        $middleware->alias([
            'capability' => RequireCapability::class,
            'access' => DeclareAccess::class,
        ]);

        // The capability is checked before any route binding reads a record, so a missing and an existing
        // record answer an account without it alike (AZ-11).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: RequireCapability::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // One answer for a missing and an out-of-scope record, naming neither (AZ-11; CS-04; WS-10).
        $exceptions->map(ModelNotFoundException::class, fn () => new NotFoundHttpException);
    })->create();
