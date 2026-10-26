<?php

namespace App\Modules\IAM\Authentication;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * The first step of ROUTE-101: an attempt inside a cooldown of SECURITY AU-05 is refused before any password is
 * checked, with MSG-20 and the minutes left — the throttled answer, which says nothing about the account.
 */
final class EnsureSignInAllowed
{
    public function __construct(private readonly LoginThrottle $throttle) {}

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $seconds = $this->throttle->blockedFor($request, AttemptLogin::identifier($request->input('email')));

        if ($seconds > 0) {
            throw self::throttled($seconds);
        }

        return $next($request);
    }

    /** MSG-20 with the minutes left: JSON 429 to the in-place dialog, the field's error to the sign-in page. */
    public static function throttled(int $seconds): HttpResponseException
    {
        $minutes = max(1, (int) ceil($seconds / 60));
        $message = trans_choice('messages.msg_20', $minutes, ['count' => $minutes]);
        $request = request();

        $response = $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['message' => $message], 429, ['Retry-After' => (string) $seconds])
            : back()->withErrors(['email' => $message])->withHeaders(['Retry-After' => (string) $seconds]);

        return new HttpResponseException($response);
    }
}
