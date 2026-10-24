<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A route's declared requirement beside a capability (SECURITY EN-03; `DIR-081` OB-03; ROUTE_CONTRACTS
 * RC-03), declared as `access:<requirement>`: `s0` — every active account, the route's content S0 or each
 * other part gated by its own read class —, `self` — the account's own row —, `enrolment` — the
 * enrolment-only session —, or `none,<exception>` for one of EN-03's unauthenticated routes. It admits
 * nothing by itself: the authentication middleware, the route's read classes and its handler do; the
 * route audit reads the declaration (SM-02).
 */
class DeclareAccess
{
    public const REQUIREMENTS = ['s0', 'self', 'enrolment', 'none'];

    /** EN-03's unauthenticated routes. */
    public const UNAUTHENTICATED = ['login', 'two_factor_challenge', 'google_redirect', 'google_callback', 'credential_link', 'health', 'csp_report'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $requirement, ?string $exception = null): Response
    {
        if (! in_array($requirement, self::REQUIREMENTS, true) || ($requirement === 'none') !== in_array($exception, self::UNAUTHENTICATED, true)) {
            throw new InvalidArgumentException("The route declares an access requirement EN-03 does not list: {$requirement}.");
        }

        return $next($request);
    }
}
