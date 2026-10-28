<?php

namespace App\Http;

use Illuminate\Http\Request;

/**
 * API_AND_INTEGRATIONS RB-05 and CONCURRENCY_IDEMPOTENCY RV-06 (H7-13; HO-06): a background request — a poll, a
 * deferred or visibility-triggered prop, a prefetch, a thumbnail or a preview — reads the session and never
 * writes it, so it extends no session. Background is decided here, on the server: a route declared background —
 * the thumbnail and preview routes — or a GET the client marks; a request that is not a GET never is, so a
 * background request can carry no command. A download the user starts is not marked.
 */
final class BackgroundRequest
{
    /** The marker the client's polling, deferred, prefetch and reload helpers send (ADMIN_FLOW §6). */
    public const HEADER = 'X-Background-Request';

    /** The route action key of a route that is background by its nature, e.g. a thumbnail. */
    public const ROUTE_FLAG = 'background';

    /**
     * The route action key of a read-only request that is not a GET — ROUTE-281's search, whose term travels in a body
     * (DP-02) —: it reads the session and never writes it either. Such a request may run beside a command's: written
     * back, the session it loaded before the command's answer was stored would erase that answer (IP-01; IP-02).
     */
    public const READS_SESSION_ONLY = 'reads_session_only';

    /** Whether the request leaves the session as it found it: a background request, or a read-only route's. */
    public static function readsSessionOnly(Request $request): bool
    {
        return self::is($request) || ($request->route()?->getAction(self::READS_SESSION_ONLY) ?? false) === true;
    }

    public static function is(Request $request): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        return $request->header(self::HEADER) === '1' || ($request->route()?->getAction(self::ROUTE_FLAG) ?? false) === true;
    }
}
