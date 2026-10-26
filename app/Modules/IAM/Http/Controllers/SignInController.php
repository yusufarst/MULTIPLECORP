<?php

namespace App\Modules\IAM\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ROUTE-001 `IAM ShowSignIn` — PG-001 *Masuk* (NAV-001): the form, the e-mail kept across the language switch and
 * never the password (INT-001), and the notice a credential link's success leaves (ROUTE-106). The page is a full
 * document at its authentication boundary (AU-06; H7-12).
 */
final class SignInController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Auth/SignIn', [
            'notice' => $request->session()->get('auth.notice') === 'password_saved' ? __('pages.pg_001.password_saved') : null,
        ]);
    }
}
