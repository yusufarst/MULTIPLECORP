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

    public static function is(Request $request): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        return $request->header(self::HEADER) === '1' || ($request->route()?->getAction(self::ROUTE_FLAG) ?? false) === true;
    }
}
