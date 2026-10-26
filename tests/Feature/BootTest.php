<?php

use Illuminate\Support\Facades\Route;

it('boots and answers a request: a guest goes to sign-in (TASK-016)', function () {
    $this->get('/')->assertRedirect('/masuk');

    expect(app()->isBooted())->toBeTrue();
});

it('registers only the routes of the Tasks built, each a ROUTE_CONTRACTS row', function () {
    $routes = array_map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri(), Route::getRoutes()->getRoutes());

    // The sign-in routes ROUTE-001, -101, -108, -109, -003 and -106 (TASK-017) — Fortify registers none of its own
    // (SM-22) —, the second factor's ROUTE-002, -104, -105, -004 and -107 (TASK-018) — Fortify's two-factor routes
    // none —, Google sign-in's ROUTE-102 and -103 (TASK-019), ROUTE-281 `Reporting Search` and ROUTE-005's fallback to PG-005 (TASK-016).
    expect($routes)->toBe([
        'GET|HEAD masuk', 'POST masuk', 'POST keluar', 'POST konfirmasi-kata-sandi', 'GET|HEAD atur-kata-sandi', 'POST atur-kata-sandi',
        'GET|HEAD masuk/verifikasi', 'POST masuk/verifikasi', 'POST masuk/verifikasi/pemulihan', 'GET|HEAD verifikasi-dua-langkah', 'POST verifikasi-dua-langkah',
        'GET|HEAD masuk/google', 'GET|HEAD masuk/google/callback',
        'GET|HEAD pengaturan/penomoran', 'PUT pengaturan/penomoran/{type}', 'POST pengaturan/penomoran/{type}/mulai',
        'POST cari', 'GET|HEAD {fallbackPlaceholder}',
    ]);
});

it('keeps the framework default migrations out: every migration is the project\'s, named by its module', function () {
    $modules = 'adm|cat|cst|doc|fin|ful|iam|inv|ops|org|prj|pty|pur';

    foreach (glob(database_path('migrations/*.php')) ?: [] as $migration) {
        expect(basename($migration))->toMatch("/^\\d{4}_\\d{2}_\\d{2}_\\d{6}_({$modules})_[a-z0-9_]+\\.php\$/")
            ->not->toStartWith('0001_01_01_');
    }
});
