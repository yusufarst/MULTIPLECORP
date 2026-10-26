<?php

use App\Http\Controllers\SystemPageController;
use App\Modules\IAM\Authentication\CredentialPageHeaders;
use App\Modules\IAM\Authentication\SessionController;
use App\Modules\IAM\Http\Controllers\ConfirmPasswordController;
use App\Modules\IAM\Http\Controllers\SetPasswordController;
use App\Modules\IAM\Http\Controllers\SignInController;
use App\Modules\IAM\Http\Controllers\TotpEnrolmentController;
use App\Modules\IAM\Http\Controllers\TwoFactorChallengeController;
use App\Modules\Reporting\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Every route is a ROUTE_CONTRACTS row, added by the Task that builds it.

// Sign-in and the session (TASK-017; SECURITY AU-04–AU-15; EN-03's unauthenticated exceptions). Fortify's
// controllers serve ROUTE-101 and ROUTE-108 behind IAM's pipeline; Fortify registers no route of its own (SM-22).
Route::get('/masuk', SignInController::class)->middleware(['guest', 'access:none,login'])->name('login');
Route::post('/masuk', [SessionController::class, 'store'])->middleware(['guest', 'access:none,login'])->name('login.store');
Route::post('/keluar', [SessionController::class, 'destroy'])->middleware(['auth', 'access:self'])->name('logout');
Route::post('/konfirmasi-kata-sandi', ConfirmPasswordController::class)->middleware(['auth', 'access:self'])->name('password.confirm');
Route::get('/atur-kata-sandi', [SetPasswordController::class, 'show'])->middleware(['access:none,credential_link', CredentialPageHeaders::class])->name('password.set.show');
Route::post('/atur-kata-sandi', [SetPasswordController::class, 'store'])->middleware(['access:none,credential_link', 'throttle:credential-link', CredentialPageHeaders::class])->name('password.set');

// The second factor (TASK-018; SECURITY AU-16, AU-18, AU-19): PG-002 for the pending attempt's account, before any
// login (EN-03), and PG-004 for the enrolment-only session alone; AU-05's throttles and its second-factor limit are
// applied by the steps themselves, counting failures alone. Fortify's own two-factor routes stay unregistered (TM-65).
Route::get('/masuk/verifikasi', [TwoFactorChallengeController::class, 'show'])->middleware(['guest', 'access:none,two_factor_challenge'])->name('two-factor.challenge');
Route::post('/masuk/verifikasi', [TwoFactorChallengeController::class, 'verify'])->middleware(['guest', 'access:none,two_factor_challenge'])->name('two-factor.verify');
Route::post('/masuk/verifikasi/pemulihan', [TwoFactorChallengeController::class, 'recovery'])->middleware(['guest', 'access:none,two_factor_challenge'])->name('two-factor.recovery');
Route::get('/verifikasi-dua-langkah', [TotpEnrolmentController::class, 'show'])->middleware(['auth', 'access:enrolment', CredentialPageHeaders::class])->name('two-factor.enrolment');
Route::post('/verifikasi-dua-langkah', [TotpEnrolmentController::class, 'confirm'])->middleware(['auth', 'access:enrolment', CredentialPageHeaders::class])->name('two-factor.enrolment.confirm');

// ROUTE-281 `Reporting Search` (TASK-016): the shell's type-ahead, PG-009 and every lookup inside a form; every
// active account (S0), each result under its own view capability (PJ-10); read-only, no `command_id`; WS-12's
// search limiter.
Route::post('/cari', SearchController::class)
    ->middleware(['auth', 'access:s0', 'throttle:search'])
    ->name('reporting.search');

// ROUTE-005, PG-005 (TASK-016): the exception handler's answer to an address no route resolves, through the web
// group so that a signed-in account sees it in the shell — every request it answers (AZ-11), in EN-03's
// authenticated group like every other route; a guest's document request goes to sign-in.
Route::fallback(SystemPageController::class)
    ->middleware(['auth', 'access:s0'])
    ->name('system.page');
