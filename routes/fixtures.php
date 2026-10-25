<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// The local stack's fixture pages for the browser suite (TEST_STRATEGY §7), loaded only in the `local`
// environment (bootstrap/app.php): no ROUTE_CONTRACTS row, never in testing, staging or production. Each serves
// a layer's check before the pages that will carry it exist. They run without the web group's session, cookies
// and CSRF token — a page before sign-in stores nothing (INT-001) —, so they touch no database: the browser
// job's stack is not migrated.

Route::middleware([SetLocale::class, HandleInertiaRequests::class])->group(function (): void {
    // TL-09–TL-12 (TASK-014): INT-001's switch on a page with a field, before sign-in, so the switch is shown to
    // keep the unsent input and the layout (LOCALIZATION §3.7).
    Route::get('__fixtures/bahasa', fn () => Inertia::render('Fixtures/LanguageSwitch'));

    // TL-09–TL-12 and TL-14 (TASK-015): the design system's component gallery in both languages, before the
    // pages that compose it exist; its pages enter only a local build (VITE_FIXTURE_PAGES; TL-13).
    Route::get('__fixtures/komponen', fn () => Inertia::render('Fixtures/Components'));
});
