<?php

namespace App\Modules\IAM\Http\Controllers;

use App\Modules\IAM\Authentication\TwoFactorAnswers;
use App\Modules\IAM\Authentication\TwoFactorChallenge;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PG-001 *Masuk* (ROUTE-001; NAV-001): the sign-in page, with the notice of a password just set (AU-12). Its view
 * also discards a pending second-factor attempt, keeping only its e-mail, so "Kembali" on PG-002 — a client-only
 * control — leaves no attempt that can still complete a sign-in (NAV-002; OQ-14, closed at Level 1 in run PC-5).
 */
final class SignInController
{
    public function __invoke(Request $request): Response
    {
        $email = $request->session()->get(TwoFactorAnswers::KEPT_EMAIL) ?? TwoFactorChallenge::email($request);
        TwoFactorChallenge::void($request);

        return Inertia::render('Auth/SignIn', [
            'notice' => $request->session()->get('auth.notice') === 'password_saved' ? __('pages.pg_001.password_saved') : null,
            'email' => is_string($email) ? $email : null,
        ]);
    }
}
