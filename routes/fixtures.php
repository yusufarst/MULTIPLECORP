<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Tests\Support\Fixtures\AccountFixture;
use Tests\Support\Fixtures\GoogleFixture;
use Tests\Support\Fixtures\ShellFixture;

// The local stack's fixture pages for the browser suite (TEST_STRATEGY §7), loaded only in the `local`
// environment (bootstrap/app.php): no ROUTE_CONTRACTS row, never in testing, staging or production. Each serves
// a layer's check before the pages that will carry it exist. They run without the web group's session, cookies
// and CSRF token — a page before sign-in stores nothing (INT-001) —, so they touch no database — but for the
// sign-in journeys' account, which the migrated stack keeps (scripts/verify.sh).

Route::middleware([SetLocale::class, HandleInertiaRequests::class])->group(function (): void {
    // TL-09–TL-12 (TASK-014): INT-001's switch on a page with a field, before sign-in, so the switch is shown to
    // keep the unsent input and the layout (LOCALIZATION §3.7).
    Route::get('__fixtures/bahasa', fn () => Inertia::render('Fixtures/LanguageSwitch'));

    // TL-09–TL-12 and TL-14 (TASK-015): the design system's component gallery in both languages, before the
    // pages that compose it exist; its pages enter only a local build (VITE_FIXTURE_PAGES; TL-13).
    Route::get('__fixtures/komponen', fn () => Inertia::render('Fixtures/Components'));

    // TL-09–TL-12 (TASK-016): the shell with a persona's hints — the page props stand in for the shared ones of a
    // signed-in account —, and a command whose session the test ends, so the re-authentication in place and the
    // step-up are proven against a stub of the sign-in (acceptance criteria 7, 8). The stub's state lives in the
    // cache under the run's key, never in a session or the database; its password is a test value of this file.
    Route::get('__fixtures/shell', fn (Request $request) => Inertia::render('Fixtures/Shell', ShellFixture::props($request)));
    Route::post('__fixtures/shell/kedaluwarsa', fn (Request $request) => ShellFixture::expire($request));
    Route::post('__fixtures/shell/masuk', fn (Request $request) => ShellFixture::signIn($request));
    Route::post('__fixtures/shell/konfirmasi', fn (Request $request) => ShellFixture::confirm($request));
    Route::post('__fixtures/shell/perintah', fn (Request $request) => ShellFixture::command($request, stepUp: false));
    Route::post('__fixtures/shell/akses', fn (Request $request) => ShellFixture::command($request, stepUp: true));
});

// TL-09–TL-12 (TASK-017): a synthetic account and its reset link for one run, so the sign-in, reset and logout
// journeys run against the real routes of PG-001 and PG-003.
Route::post('__fixtures/akun', [AccountFixture::class, 'create']);

// TL-09–TL-12 (TASK-019): the faked Google — sign-in on with a synthetic client and the token exchange answered
// locally — for the Google journey, armed and disarmed by the run.
GoogleFixture::apply();
Route::post('__fixtures/google', [GoogleFixture::class, 'arm']);
