<?php

namespace App\Http\Middleware;

use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Illuminate\Support\defer;

/**
 * A route's capability requirement (SECURITY EN-01, EN-03; ROUTE_CONTRACTS RC-03), declared as
 * `capability:<code>` after the authentication middleware: an account without the capability is
 * forbidden (AZ-11), and the denial is an ACCESS_DENIED security event written after the response
 * (LG-02). The route audit reads the declaration (SM-02).
 */
class RequireCapability
{
    public function __construct(private readonly SecurityEventRecorder $events) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        if (! CapabilityCatalogue::has($capability)) {
            throw new LogicException("The route declares {$capability}, which is not a capability of PERMISSIONS_MATRIX §4.");
        }

        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (Gate::forUser($user)->denies($capability)) {
            $route = $request->route()?->getName();
            $userId = $user->getAuthIdentifier();
            defer(fn () => $this->events->record(
                SecurityEventKind::AccessDenied,
                userId: is_int($userId) ? $userId : null,
                reasonCode: 'CAPABILITY',
                routeName: $route,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                details: ['capability' => $capability],
            ))->always();

            // An Owner-only address answers an Admin as the not-found page, identical to an unknown one; any other
            // missing capability is forbidden (INFORMATION_ARCHITECTURE §6.3; UXN-18; IP-21; TASK-016).
            throw CapabilityCatalogue::isOwnerOnly($capability) ? new NotFoundHttpException : new AuthorizationException;
        }

        return $next($request);
    }
}
