<?php

namespace App\Modules\IAM\Authentication;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answers of the code steps, truthfully one for every failing code (SECURITY AU-04, AU-05; HO-40): the
 * generic code answer at the code field for a wrong, reused or stale code, a recovery code that matches nothing
 * and a challenge voided meanwhile; MSG-20 with the minutes left while a cooldown or a limit runs; an expired or
 * missing challenge sends PG-002 back to PG-001 with the e-mail kept (NAV-002). The in-place dialog of IP-11 gets
 * them as JSON, the pages as their form's errors.
 */
final class TwoFactorAnswers
{
    /** The flash key PG-001 reads its kept e-mail from. */
    public const KEPT_EMAIL = 'auth.kept_email';

    public static function mismatch(Request $request, string $field = 'code'): Response
    {
        $message = __('auth.code_mismatch');

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? new JsonResponse(['message' => $message, 'errors' => [$field => [$message]]], 422)
            : back()->withErrors([$field => $message]);
    }

    public static function throttled(Request $request, int $seconds, string $field = 'code'): Response
    {
        $minutes = max(1, (int) ceil($seconds / 60));
        $message = trans_choice('messages.msg_20', $minutes, ['count' => $minutes]);

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? new JsonResponse(['message' => $message], 429, ['Retry-After' => (string) $seconds])
            : back()->withErrors([$field => $message])->withHeaders(['Retry-After' => (string) $seconds]);
    }

    public static function expired(Request $request): Response
    {
        $email = TwoFactorChallenge::email($request);
        TwoFactorChallenge::void($request);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse(['message' => __('auth.code_mismatch'), 'expired' => true], 422);
        }

        if ($email !== null) {
            $request->session()->flash(self::KEPT_EMAIL, $email);
        }

        return $request->header('X-Inertia') ? Inertia::location('/masuk') : redirect('/masuk');
    }
}
