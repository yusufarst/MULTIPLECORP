<?php

namespace App\Http\Middleware;

use App\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A new correlation id for every request (ARCHITECTURE §17; SECURITY LG-04), never taken from the
 * client, so that its audit events, security events and log lines are linked.
 */
class AssignCorrelationId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        CorrelationId::start();

        return $next($request);
    }
}
