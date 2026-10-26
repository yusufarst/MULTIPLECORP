<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers of SECURITY WS-08 that the application owns: the Content-Security-Policy
 * with a per-request nonce, shared with the Vite tags of the page, and no-store caching.
 * Nginx sets the static headers (H9-02).
 */
class SecurityHeaders
{
    /** The internal marker of a response carrying FL-07's own header profile, removed before it leaves. */
    public const FILE_PROFILE = 'X-Internal-File-Profile';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        // A private file keeps the stricter header profile its controller set (SECURITY FL-07; TASK-022).
        if ($response->headers->get(self::FILE_PROFILE) !== null) {
            $response->headers->remove(self::FILE_PROFILE);

            return $response;
        }

        $response->headers->set('Content-Security-Policy', self::policy($nonce));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    public static function policy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
