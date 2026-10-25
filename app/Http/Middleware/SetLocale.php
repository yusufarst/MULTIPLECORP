<?php

namespace App\Http\Middleware;

use App\Support\Localization\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The request's interface language (LOCALIZATION §2): signed in, the session's — filled at sign-in from the
 * account's `users.locale` —, else the account's own, else Indonesian; before sign-in, the whitelisted address
 * key `bahasa`, never stored, an unknown value ignored (INT-001). A choice made before sign-in never reaches
 * the account. Messages, validation errors and flash text are then written in this language (§3.8).
 */
class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $locale = $user === null
            ? Locales::supported($request->query(Locales::ADDRESS_KEY))
            : Locales::supported($request->hasSession() ? $request->session()->get(Locales::SESSION_KEY) : null)
                ?? Locales::supported($user->getAttribute('locale'));

        App::setLocale($locale ?? Locales::DEFAULT);

        return $next($request);
    }
}
