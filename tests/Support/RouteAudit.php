<?php

namespace Tests\Support;

use App\Http\Middleware\DeclareAccess;
use App\Modules\IAM\Data\CapabilityCatalogue;
use Illuminate\Routing\Route;

/**
 * The route audit of SECURITY_TEST_MATRIX SM-02 (H8-02; SECURITY EN-03; `DIR-081` OB-03): every registered
 * route declares exactly one of a catalogue capability, "every active account (S0)", "SELF", "the
 * enrolment-only session" — each behind authentication — or EN-03's "none" for one of its unauthenticated
 * routes, outside authentication; no Fortify two-factor route is registered (AU-16). A violation fails the
 * route-integrity layer of the build (TL-07).
 */
final class RouteAudit
{
    /**
     * @param  iterable<Route>  $routes
     * @return list<string>
     */
    public static function violations(iterable $routes): array
    {
        $violations = [];

        foreach ($routes as $route) {
            $name = implode('|', $route->methods()).' '.$route->uri();
            $middleware = array_map('strval', $route->gatherMiddleware());
            $declarations = array_values(array_filter($middleware, fn (string $entry): bool => str_starts_with($entry, 'capability:') || str_starts_with($entry, 'access:')));
            $authenticated = array_filter($middleware, fn (string $entry): bool => $entry === 'auth' || str_starts_with($entry, 'auth:')) !== [];

            // An excluded middleware is removed only at dispatch, so a declaration could be counted here and
            // never run there; no route excludes any.
            if ($route->excludedMiddleware() !== []) {
                $violations[] = "{$name}: excludes middleware, so its declaration may not run";
            }

            if (str_starts_with((string) $route->getName(), 'two-factor.')) {
                $violations[] = "{$name}: a Fortify two-factor route is registered (AU-16)";
            }

            if (count($declarations) !== 1) {
                $violations[] = sprintf('%s: declares %d requirements, not one (OB-03)', $name, count($declarations));

                continue;
            }

            [$kind, $argument] = explode(':', $declarations[0], 2) + [1 => ''];
            $parameters = explode(',', $argument);

            if ($kind === 'capability' && ! CapabilityCatalogue::has($parameters[0])) {
                $violations[] = "{$name}: {$parameters[0]} is not a catalogue capability";
            }

            if ($kind === 'access' && ! in_array($parameters[0], DeclareAccess::REQUIREMENTS, true)) {
                $violations[] = "{$name}: {$parameters[0]} is not a requirement EN-03 accepts";
            }

            $unauthenticated = $kind === 'access' && $parameters[0] === 'none';

            if ($unauthenticated && ! in_array($parameters[1] ?? null, DeclareAccess::UNAUTHENTICATED, true)) {
                $violations[] = "{$name}: an unauthenticated route EN-03 does not list";
            }

            if ($unauthenticated === $authenticated) {
                $violations[] = $unauthenticated ? "{$name}: an EN-03 exception behind authentication" : "{$name}: not behind authentication";
            }
        }

        return $violations;
    }
}
