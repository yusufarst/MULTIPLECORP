<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The absolute session limit as the open page learns it (ADMIN_FLOW UXH-11, IP-11; SECURITY AU-08): a
 * non-sensitive timestamp with each authenticated response, in a header outside WS-07's page data, from the
 * sign-in time the session keeps — written at sign-in (TASK-017) —, so the shell can announce MSG-34 fifteen and
 * five minutes before the 12-hour limit. A session without that time sends nothing.
 *
 * UXH-02 (TASK-093): beside it, the session's account as an opaque key — a keyed digest of the account, no business
 * data, outside WS-07's page data like the deadline —, so the in-place re-authentication resumes the covered page only
 * for the very account it belongs to and makes the full document load for any other (ADMIN_FLOW §6).
 */
class SessionDeadline
{
    public const HEADER = 'X-Session-Deadline';

    public const ACCOUNT_HEADER = 'X-Session-Account';

    /** The session key of the sign-in time, a Unix timestamp (RV-06: "the login time kept in the session"). */
    public const SIGNED_IN_AT = 'auth.signed_in_at';

    /** AU-08: twelve hours, not extendable. */
    public const ABSOLUTE_HOURS = 12;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $deadline = self::of($request);

        if ($deadline !== null) {
            $response->headers->set(self::HEADER, $deadline);
        }

        $account = self::accountOf($request);

        if ($account !== null) {
            $response->headers->set(self::ACCOUNT_HEADER, $account);
        }

        return $response;
    }

    /** The signed-in account's opaque key — never its identifier —, also for the document's first load; none for a guest. */
    public static function accountOf(Request $request): ?string
    {
        $user = $request->user();
        $key = config('app.key');

        if ($user === null || ! is_string($key) || $key === '') {
            return null;
        }

        $id = $user->getAuthIdentifier();

        return is_int($id) || is_string($id) ? substr(hash_hmac('sha256', 'session-account|'.$id, $key), 0, 32) : null;
    }

    /** The deadline of the request's session, also for the document's first load (the root view's meta tag). */
    public static function of(Request $request): ?string
    {
        $signedInAt = $request->hasSession() && $request->user() !== null ? $request->session()->get(self::SIGNED_IN_AT) : null;

        return is_int($signedInAt) ? CarbonImmutable::createFromTimestamp($signedInAt)->addHours(self::ABSOLUTE_HOURS)->toIso8601ZuluString() : null;
    }
}
