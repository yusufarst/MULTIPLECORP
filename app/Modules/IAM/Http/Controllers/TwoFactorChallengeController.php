<?php

namespace App\Modules\IAM\Http\Controllers;

use App\Modules\IAM\Authentication\TwoFactorAnswers;
use App\Modules\IAM\Authentication\TwoFactorChallenge;
use App\Modules\IAM\Authentication\TwoFactorSteps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * PG-002 *Verifikasi Dua Langkah* (NAV-002): ROUTE-002 shows the code step of the pending attempt, with "Gunakan
 * kode pemulihan" after a password only — nothing of the account but its display name and the locale (WS-07) —;
 * ROUTE-104 `IAM VerifyTwoFactorChallenge` takes a TOTP code and ROUTE-105 `IAM UseRecoveryCode` a recovery
 * code (AU-16, AU-19). Without a pending attempt the page answers PG-001, the e-mail kept.
 */
final class TwoFactorChallengeController
{
    public function show(Request $request): Response
    {
        $challenge = TwoFactorChallenge::current($request);

        if ($challenge === null) {
            return TwoFactorAnswers::expired($request);
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'recovery' => $challenge['purpose'] === TwoFactorChallenge::PURPOSE_PASSWORD,
        ])->toResponse($request);
    }

    public function verify(Request $request, TwoFactorSteps $steps): Response
    {
        $request->validate(['code' => ['required', 'string', 'max:16']], ['code.required' => __('forms.required')]);

        return $steps->verifyCode($request, preg_replace('/\s+/', '', $request->string('code')->toString()) ?? '');
    }

    public function recovery(Request $request, TwoFactorSteps $steps): Response
    {
        $request->validate(['recovery_code' => ['required', 'string', 'max:64']], ['recovery_code.required' => __('forms.required')]);

        return $steps->useRecoveryCode($request, $request->string('recovery_code')->toString());
    }
}
