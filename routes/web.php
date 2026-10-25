<?php

use App\Http\Controllers\SystemPageController;
use App\Modules\Reporting\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Every route is a ROUTE_CONTRACTS row, added by the Task that builds it.

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
