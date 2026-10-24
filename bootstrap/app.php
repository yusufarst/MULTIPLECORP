<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\DeclareAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireCapability;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global, so that error pages carry the policy too (SECURITY WS-08).
        $middleware->prepend(SecurityHeaders::class);

        // First of all, so that every later log line of the request carries it (ARCHITECTURE §17).
        $middleware->prepend(AssignCorrelationId::class);

        $middleware->web(append: [
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
