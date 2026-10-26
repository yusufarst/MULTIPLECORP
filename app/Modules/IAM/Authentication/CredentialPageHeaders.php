<?php

namespace App\Modules\IAM\Authentication;

use Closure;
use Illuminate\Http\RedirectResponse;
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

    /**
     * MSG-20 with the minutes left, on PG-003's link alert as a form error — the route's limiter and H6-12's cap
     * alike —, so the page keeps its token in memory and the person tries again from the same link (AU-12).
     */
    public static function throttled(int $seconds): RedirectResponse
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return back()->withErrors(['token' => trans_choice('messages.msg_20', $minutes, ['count' => $minutes])])
            ->withHeaders(['Retry-After' => (string) $seconds, 'Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store']);
    }
}
