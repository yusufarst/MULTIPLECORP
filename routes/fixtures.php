<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// The local stack's fixture pages for the browser suite (TEST_STRATEGY §7), loaded only in the `local`
// environment (bootstrap/app.php): no ROUTE_CONTRACTS row, never in testing, staging or production. Each serves
// a layer's check before the pages that will carry it exist.

// TL-09/TL-10 (TASK-014): INT-001's switch on a page with a field, before sign-in, so the switch is shown to
// keep the unsent input and the layout (LOCALIZATION §3.7).
Route::get('__fixtures/bahasa', fn () => Inertia::render('Fixtures/LanguageSwitch'));
