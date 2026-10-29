<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Tests\Support\Fixtures\AccountFixture;
use Tests\Support\Fixtures\CaseFixture;
use Tests\Support\Fixtures\DocumentFixture;
use Tests\Support\Fixtures\EvidenceFixture;
use Tests\Support\Fixtures\FilesFixture;
use Tests\Support\Fixtures\GoogleFixture;
use Tests\Support\Fixtures\NumberingFixture;
use Tests\Support\Fixtures\ConfirmationFixture;
use Tests\Support\Fixtures\ProjectFixture;
use Tests\Support\Fixtures\QuotationFixture;
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
// Never in the console: package discovery loads these routes, after a `--no-dev` install too, without the fixture classes.
if (! app()->runningInConsole()) {
    GoogleFixture::apply();
}
Route::post('__fixtures/google', [GoogleFixture::class, 'arm']);

// TL-09–TL-12 (TASK-021): PG-061's company for the run and go-live, whose store is TASK-077's, recorded for the
// requests while the run has armed it.
if (! app()->runningInConsole()) {
    NumberingFixture::apply();
}
Route::post('__fixtures/penomoran', [NumberingFixture::class, 'arm']);

// TL-09–TL-12 (TASK-029): the synthetic subjects of the issuing Tasks for the requests of an armed run.
if (! app()->runningInConsole()) {
    DocumentFixture::apply();
}

// TL-09–TL-12 (TASK-030): PG-038's company for the run, with a reference-less client order for IP-05's warning.
Route::post('__fixtures/bukti', [EvidenceFixture::class, 'create']);

// TL-09–TL-12 (TASK-022): a sample page for an upload through the pipeline, its download by ROUTE-226 and a
// rendition's state by ROUTE-285, for a signed-in account; the upload route and the evidence registration are TASK-030's.
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('__fixtures/berkas', [FilesFixture::class, 'page']);
    // The run's own requests carry no form token; the application's routes keep the check (RequestForgeryTest).
    Route::post('__fixtures/berkas', [FilesFixture::class, 'upload'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/berkas/rendisi', [FilesFixture::class, 'rendition'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-026): stand-ins of PG-013's and PG-017's party fields, for J-MD-02's and J-MD-03's inline
    // creation through the real ROUTE-141 and ROUTE-143, before the project and purchase forms exist.
    Route::get('__fixtures/formulir-pihak', fn () => Inertia::render('Fixtures/PartyForms'));

    // TL-09–TL-12 (TASK-029; J-DOC-01; CJ-15): a draft prepared from a synthetic project through ROUTE-221's action, and
    // its rendition rendered or failed as the absent queue worker would, before the issuing pages exist.
    Route::post('__fixtures/dokumen', [DocumentFixture::class, 'draft'])->withoutMiddleware(PreventRequestForgery::class);
    Route::post('__fixtures/dokumen/rendisi', [DocumentFixture::class, 'rendition'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-031): two correction cases of the run's company, one closable and one carrying a residual.
    Route::post('__fixtures/kasus', [CaseFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-032): the run's company with its PROJECT numbering started or not, a client, goods and a service,
    // the account's grants on it, and optionally a project created through ROUTE-151's action.
    Route::post('__fixtures/proyek', [ProjectFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-033): the run's company with its PROJECT and DOC-01 numbering started, a project of `lines` goods
    // and, by `stage`, its quotation drafted with a validity and prices or also issued through the quotation's actions.
    Route::post('__fixtures/penawaran', [QuotationFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);

    // TL-09–TL-12 (TASK-034): a mixed project with the client's SPK registered as order evidence, confirmed when asked.
    Route::post('__fixtures/konfirmasi', [ConfirmationFixture::class, 'create'])->withoutMiddleware(PreventRequestForgery::class);
});
