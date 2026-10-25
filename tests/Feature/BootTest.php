<?php

use Illuminate\Support\Facades\Route;

it('boots and answers a request: a guest goes to sign-in (TASK-016)', function () {
    $this->get('/')->assertRedirect('/masuk');

    expect(app()->isBooted())->toBeTrue();
});

it('registers only the routes of the Tasks built, each a ROUTE_CONTRACTS row', function () {
    $routes = array_map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri(), Route::getRoutes()->getRoutes());

    // ROUTE-281 `Reporting Search` and ROUTE-005's fallback to PG-005 (TASK-016).
    expect($routes)->toBe(['POST cari', 'GET|HEAD {fallbackPlaceholder}']);
});

it('keeps the framework default migrations out: every migration is the project\'s, named by its module', function () {
    $modules = 'adm|cat|cst|doc|fin|ful|iam|inv|ops|org|prj|pty|pur';

    foreach (glob(database_path('migrations/*.php')) ?: [] as $migration) {
        expect(basename($migration))->toMatch("/^\\d{4}_\\d{2}_\\d{2}_\\d{6}_({$modules})_[a-z0-9_]+\\.php\$/")
            ->not->toStartWith('0001_01_01_');
    }
});
