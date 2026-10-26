<?php

namespace App\Modules\IAM\Http\Controllers;

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Authentication\TwoFactorAnswers;
use App\Modules\IAM\Authentication\TwoFactorChallenge;
use App\Modules\IAM\Authentication\TwoFactorSteps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * PG-004 *Aktifkan Verifikasi Dua Langkah* (NAV-004; AU-18): the enrolment-only session's single path. ROUTE-004
 * `IAM ShowTotpEnrolment` shows the QR code and the text key as flash data, never a page prop kept in history
 * (AU-16; AU-12's rule); ROUTE-107 `IAM ConfirmTotpEnrolment` confirms one code, the password confirmed again
 * after 15 minutes (AU-15; H7-15), and shows the eight recovery codes once, as flash data, before the landing
 * page (AU-19). A session that is not enrolment-only goes to the landing page.
 */
final class TotpEnrolmentController
{
    public function show(Request $request, TwoFactorSteps $steps): Response
    {
        if (! TwoFactorChallenge::enrolmentOnly($request)) {
            return redirect('/');
        }

        $enrolment = $steps->enrolment($request);

        return Inertia::render('Auth/TotpEnrolment', ['stage' => 'enrol', 'replacing' => $enrolment['replacing']])
            ->flash(['qr' => $enrolment['qr'], 'key' => $enrolment['key']])
            ->toResponse($request);
    }

    public function confirm(Request $request, TwoFactorSteps $steps): Response|RedirectResponse
    {
        if (! TwoFactorChallenge::enrolmentOnly($request)) {
            return response()->json(['message' => __('messages.msg_10')], Response::HTTP_FORBIDDEN);
        }

        $request->validate(['code' => ['required', 'string', 'max:16']], ['code.required' => __('forms.required')]);

        // The login's password step counts as the confirmation for 15 minutes, then ROUTE-109 confirms it again.
        $confirmedAt = $request->session()->get(RequireStepUp::CONFIRMED_AT);

        if (! is_numeric($confirmedAt) || now()->getTimestamp() - (int) $confirmedAt > 900) {
            return back()->withErrors(['password' => __('messages.msg_27')]);
        }

        $codes = $steps->confirmEnrolment($request, preg_replace('/\s+/', '', $request->string('code')->toString()) ?? '');

        if ($codes === null) {
            return TwoFactorAnswers::mismatch($request);
        }

        return Inertia::render('Auth/TotpEnrolment', ['stage' => 'codes', 'replacing' => false])
            ->flash(['recovery_codes' => $codes])
            ->toResponse($request);
    }
}
