<?php

namespace App\Modules\IAM\Http\Controllers;

use App\Modules\IAM\Authentication\GoogleIdentities;
use App\Modules\IAM\Authentication\OwnAccount;
use App\Modules\IAM\Authentication\SecondFactorRefused;
use App\Modules\IAM\Authentication\TwoFactorAnswers;
use App\Modules\IAM\Authentication\TwoFactorSteps;
use App\Modules\IAM\Queries\OwnAccountView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * PG-065 *Akun Saya* (ROUTE-065; NAV-065) and its self-service of SECURITY AU-13 — ROUTE-111 the password, ROUTE-112
 * TOTP's enrolment or device replacement, ROUTE-113 and ROUTE-114 the recovery codes, ROUTE-115 TOTP off, ROUTE-116
 * and ROUTE-117 the Google link and unlink, ROUTE-118 the display language —, every one on the account's own row
 * (SELF; PJ-17), the sensitive ones behind the step-up (AU-15), none carrying a `command_id` (CI-09; RB-03). The QR
 * code, the key and every set of recovery codes travel as flash data, shown once, never a page prop kept in history
 * (AU-12's rule; AU-16; AU-19); each answer is the page's flash `answer` or the field's error, in place (IP-01).
 */
final class OwnAccountController
{
    public function __construct(private readonly OwnAccount $account) {}

    public function show(Request $request, OwnAccountView $view): Response
    {
        return Inertia::render('IAM/OwnAccount', $view->page($request))->toResponse($request);
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', OwnAccount::currentPassword($request)],
            'password' => ['required', 'string', 'confirmed', OwnAccount::passwordProfile($request)],
        ], ['required' => __('forms.required'), 'password.confirmed' => __('account.password.mismatch')]);

        $this->account->changePassword($request, $request->string('password')->toString());

        return $this->done('account.password.changed');
    }

    public function enrol(Request $request): Response|RedirectResponse
    {
        $code = self::code($request, 'code');

        try {
            if ($code !== null) {
                $codes = app(TwoFactorSteps::class)->confirmEnrolment($request, $code);

                if ($codes === null) {
                    return TwoFactorAnswers::mismatch($request);
                }

                Inertia::flash('recovery_codes', $codes);

                return $this->done('account.totp.enrolled');
            }

            $enrolment = $this->account->startEnrolment($request, self::code($request, 'current_code'));
        } catch (SecondFactorRefused $refused) {
            return TwoFactorAnswers::throttled($request, $refused->seconds, $code !== null ? 'code' : 'current_code');
        }

        if ($enrolment === OwnAccount::WRONG_CODE) {
            return TwoFactorAnswers::mismatch($request, 'current_code');
        }

        Inertia::flash('enrolment', $enrolment);

        return redirect('/akun', 303);
    }

    public function viewCodes(Request $request): Response|RedirectResponse
    {
        return $this->withCode($request, fn (string $code): array|string => $this->account->viewRecoveryCodes($request, $code), 'account.codes.shown');
    }

    public function regenerateCodes(Request $request): Response|RedirectResponse
    {
        return $this->withCode($request, fn (string $code): array|string => $this->account->regenerateRecoveryCodes($request, $code), 'account.codes.regenerated');
    }

    public function disableTotp(Request $request): Response|RedirectResponse
    {
        return $this->withCode($request, fn (string $code): string => $this->account->disableTotp($request, $code), 'account.totp.disabled');
    }

    public function linkGoogle(Request $request): Response|RedirectResponse
    {
        $answer = $this->guarded($request, fn (string $code): string => $this->account->linkGoogle($request, $code));

        if ($answer instanceof Response) {
            return $answer;
        }

        return match (is_string($answer) ? $answer : '') {
            GoogleIdentities::LINKED, GoogleIdentities::NO_OP => $this->done('account.google.linked'),
            OwnAccount::NO_TOTP => $this->refused('account.google.no_attempt'),
            // AU-23's checks answer generically: a conflict, a second identity and a refused account alike.
            default => $this->refused('account.google.refused'),
        };
    }

    public function unlinkGoogle(Request $request): RedirectResponse
    {
        $this->account->unlinkGoogle($request);

        return $this->done('account.google.unlinked');
    }

    public function locale(Request $request): RedirectResponse
    {
        $request->validate(['locale' => ['required', Rule::in(['id', 'en', 'default'])]], ['locale.in' => __('account.locale.unknown'), 'required' => __('forms.required')]);
        $chosen = $request->string('locale')->toString();
        $this->account->setLocale($request, $chosen === 'default' ? null : $chosen);

        return back(303);
    }

    /**
     * A step that takes a current code: its codes flashed once, or its answer in place.
     *
     * @param  \Closure(string): (list<string>|string)  $step
     */
    private function withCode(Request $request, \Closure $step, string $done): Response|RedirectResponse
    {
        $answer = $this->guarded($request, $step);

        if (! is_string($answer) && ! is_array($answer)) {
            return $answer;
        }

        if (is_array($answer)) {
            Inertia::flash('recovery_codes', $answer);

            return $this->done($done);
        }

        return match ($answer) {
            OwnAccount::DONE => $this->done($done),
            OwnAccount::HIGH_RISK => $this->refused('account.totp.high_risk'),
            default => $this->refused('account.totp.none'),
        };
    }

    /**
     * @param  \Closure(string): (list<string>|string)  $step
     * @return list<string>|string|Response
     */
    private function guarded(Request $request, \Closure $step): array|string|Response
    {
        $request->validate(['code' => ['required', 'string', 'max:16']], ['code.required' => __('forms.required')]);

        try {
            $answer = $step((string) self::code($request, 'code'));
        } catch (SecondFactorRefused $refused) {
            return TwoFactorAnswers::throttled($request, $refused->seconds);
        }

        return $answer === OwnAccount::WRONG_CODE ? TwoFactorAnswers::mismatch($request) : $answer;
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('answer', ['kind' => 'COMMITTED', 'text' => __($key)]);

        return redirect('/akun', 303);
    }

    private function refused(string $key): RedirectResponse
    {
        Inertia::flash('answer', ['kind' => 'REJECTED', 'text' => __($key)]);

        return redirect('/akun', 303);
    }

    private static function code(Request $request, string $field): ?string
    {
        $value = $request->input($field);

        return is_string($value) && trim($value) !== '' ? (string) preg_replace('/\s+/', '', $value) : null;
    }
}
