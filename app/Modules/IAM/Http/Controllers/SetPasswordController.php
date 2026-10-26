<?php

namespace App\Modules\IAM\Http\Controllers;

use App\Modules\IAM\Authentication\AttemptLogin;
use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Authentication\PasswordProfile;
use App\Modules\IAM\Authentication\VerificationRefused;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PG-003 *Atur Kata Sandi* (NAV-003; AU-12): ROUTE-003 shows the form — the token stays in the address's fragment,
 * which no request carries, and the page moves it into memory —; ROUTE-106 `IAM ConsumeCredentialLink` takes it
 * from the request body, answers an unknown, expired, used or revoked link alike with MSG-28 before any rule,
 * checks the new password under AU-03 before any transaction, and sets it. A session this browser held ends with
 * it — its identifier regenerated, a step-up confirmation it held cleared (AU-06, AU-15). The token is never
 * flashed back (WS-10) and the pages send `Referrer-Policy: no-referrer` and `no-store` (CredentialPageHeaders).
 */
final class SetPasswordController
{
    public function show(): Response
    {
        return Inertia::render('Auth/SetPassword');
    }

    public function store(Request $request, CredentialLinks $links): RedirectResponse
    {
        $token = $request->input('token');
        if (! is_string($token) || preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
            throw ValidationException::withMessages(['token' => __('messages.msg_28')]);
        }

        $account = $links->accountFor($token);

        if ($account === null) {
            $links->rejected($request->ip(), $request->userAgent());

            throw ValidationException::withMessages(['token' => __('messages.msg_28')]);
        }

        $request->validate(
            ['password' => ['required', 'string', 'confirmed', new PasswordProfile($account)]],
            ['password.required' => __('forms.required'), 'password.confirmed' => __('auth_rules.password_mismatch')],
        );

        try {
            $account = $links->consume($token, AttemptLogin::password($request->input('password')), $request->ip(), $request->userAgent());
        } catch (VerificationRefused) {
            throw new ThrottleRequestsException(headers: ['Retry-After' => '1']);
        }

        if ($account === null) {
            throw ValidationException::withMessages(['token' => __('messages.msg_28')]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/masuk')->with('auth.notice', 'password_saved');
    }
}
