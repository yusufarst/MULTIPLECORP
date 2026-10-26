<?php

namespace App\Http\Responses;

use App\Support\CorrelationId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The shell's answers outside a page's own (ADMIN_FLOW UXH-11, IP-10, IP-11, IP-21; SECURITY WS-10; TASK-016):
 * an unauthenticated Inertia or JSON request answers 401 — never a redirect — so the open page signs in again in
 * place with its input kept; an expired request-forgery token, 419, the same; and an address the account cannot
 * open answers PG-005 — not found, identical for an unknown, an out-of-scope and an Owner-only address, forbidden
 * for a record in scope without the capability, or the error with its reference code —, nothing of the record
 * and no technical detail. The step-up's own status is `RequireStepUp`'s.
 */
final class SystemAnswer
{
    /** The page component of PG-005. */
    public const PAGE = 'System/Page';

    public static function unauthenticated(AuthenticationException $exception, Request $request): Response
    {
        if (self::inPlace($request)) {
            return response()->json(['message' => __('messages.msg_11')], Response::HTTP_UNAUTHORIZED);
        }

        // A document request — an address typed or opened from outside — goes to sign-in (PG-001; TASK-017).
        return redirect()->guest('/masuk');
    }

    public static function respond(Response $response, Throwable $exception, Request $request): Response
    {
        $status = $response->getStatusCode();

        if ($status === 419 && self::inPlace($request)) {
            return response()->json(['message' => __('messages.msg_11')], 419);
        }

        $kind = match (true) {
            $status === 404 => 'not_found',
            $status === 403 => 'forbidden',
            $status >= 500 && ! config('app.debug') => 'error',
            default => null,
        };

        if ($kind === null || ($request->expectsJson() && ! $request->hasHeader('X-Inertia'))) {
            return $response;
        }

        return self::page($kind, $status, $request);
    }

    private static function page(string $kind, int $status, Request $request): Response
    {
        return Inertia::render(self::PAGE, ['kind' => $kind, 'reference' => CorrelationId::current()])
            ->toResponse($request)
            ->setStatusCode($status);
    }

    /** An Inertia visit or a JSON request answers in place; a document request does not. */
    private static function inPlace(Request $request): bool
    {
        return $request->hasHeader('X-Inertia') || $request->expectsJson();
    }
}
