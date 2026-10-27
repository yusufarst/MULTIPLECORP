<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AU-15's step-up before a command that changes access, money destinations or opening data (ADMIN_FLOW IP-10;
 * UXH-11): when the account's password was not confirmed in the last 15 minutes, the command answers 423 with
 * MSG-27 — a status the client intercepts as it intercepts a 401, never a redirect —, and the client opens
 * "Konfirmasi Kata Sandi" above any open dialog, then sends the same `command_id` and payload again. The
 * confirmation itself is the framework's password confirmation (TASK-017), which records the time read here.
 */
class RequireStepUp
{
    /** The framework's own record of the last password confirmation. */
    public const CONFIRMED_AT = 'auth.password_confirmed_at';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return self::recent($request) ? $next($request) : self::ask();
    }

    /** Whether the password was confirmed within the step-up's window — for a route whose step-up is conditional (ROUTE-132). */
    public static function recent(Request $request): bool
    {
        $confirmedAt = $request->session()->get(self::CONFIRMED_AT, 0);
        $timeout = config('auth.password_timeout');

        return now()->getTimestamp() - (is_numeric($confirmedAt) ? (int) $confirmedAt : 0) <= (is_numeric($timeout) ? (int) $timeout : 0);
    }

    /** MSG-27's 423, the client's cue to confirm the password and send the same request again (IP-10). */
    public static function ask(): Response
    {
        return response()->json(['message' => __('messages.msg_27')], Response::HTTP_LOCKED);
    }
}
