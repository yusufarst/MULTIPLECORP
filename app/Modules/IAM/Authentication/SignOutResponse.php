<?php

namespace App\Modules\IAM\Authentication;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer after ROUTE-108's logout (INT-018; AU-06): PG-001 as a full document load, nothing of the ended
 * session's pages kept in history (H7-12); the shell's own request, which reloads the sign-in itself, gets 204.
 */
final class SignOutResponse implements LogoutResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $target = url(Fortify::redirects('logout', '/masuk'));

        if ($request->header('X-Inertia')) {
            return Inertia::location($target);
        }

        return $request->wantsJson() ? new JsonResponse(status: 204) : redirect($target);
    }
}
