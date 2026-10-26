<?php

namespace App\Modules\IAM\Authentication;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SECURITY AU-12's headers of the credential pages: `Referrer-Policy: no-referrer` — the reverse proxy keeps an
 * application's own policy and adds WS-08's `same-origin` only where none is sent — and `Cache-Control: no-store`.
 */
final class CredentialPageHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
