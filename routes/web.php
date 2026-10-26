<?php

use App\Http\Controllers\SystemPageController;
use App\Modules\IAM\Authentication\CredentialPageHeaders;
use App\Modules\IAM\Authentication\SessionController;
use App\Modules\IAM\Http\Controllers\ConfirmPasswordController;
use App\Modules\IAM\Http\Controllers\SetPasswordController;
use App\Modules\IAM\Http\Controllers\SignInController;
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
