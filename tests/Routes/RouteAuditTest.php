<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Tests\Support\RouteAudit;

// TL-07 (TASK-011): SM-02's route audit over every registered route (H8-02; EN-03; OB-03), and on fixture
// routes each declaration it refuses.

it('finds every registered route declared (SM-02)', function () {
    expect(RouteAudit::violations(Route::getRoutes()->getRoutes()))->toBe([]);
});

it('accepts each declaration EN-03 allows', function () {
    $routes = new RouteCollection;
    $routes->add((new RouteDefinition(['GET'], 'proyek', fn () => ''))->middleware(['auth', 'capability:projects.view']));
    $routes->add((new RouteDefinition(['GET'], 'beranda-umum', fn () => ''))->middleware(['auth', 'access:s0']));
    $routes->add((new RouteDefinition(['PUT'], 'akun/bahasa', fn () => ''))->middleware(['auth', 'access:self']));
    $routes->add((new RouteDefinition(['GET'], 'verifikasi-dua-langkah', fn () => ''))->middleware(['auth', 'access:enrolment']));
    $routes->add((new RouteDefinition(['GET'], 'masuk', fn () => ''))->middleware(['access:none,login']));

    expect(RouteAudit::violations($routes->getRoutes()))->toBe([]);
});

it('refuses a route that declares nothing, too much or what EN-03 does not list', function (array $middleware, string $violation, ?string $name = null, array $excluded = []) {
    $route = (new RouteDefinition(['GET'], 'contoh', fn () => ''))->middleware($middleware)->withoutMiddleware($excluded);

    if ($name !== null) {
        $route->name($name);
    }

    expect(implode("\n", RouteAudit::violations([$route])))->toContain($violation);
})->with([
    'no declaration' => [['auth'], 'declares 0 requirements'],
    'two declarations' => [['auth', 'capability:stock.view', 'access:s0'], 'declares 2 requirements'],
    'an unknown capability' => [['auth', 'capability:stock.teleport'], 'not a catalogue capability'],
    'an unknown requirement' => [['auth', 'access:everyone'], 'not a requirement EN-03 accepts'],
    'a capability without authentication' => [['capability:stock.view'], 'not behind authentication'],
    'an unlisted unauthenticated route' => [['access:none,backdoor'], 'an unauthenticated route EN-03 does not list'],
    'an EN-03 exception behind authentication' => [['auth', 'access:none,login'], 'an EN-03 exception behind authentication'],
    'an excluded authentication' => [['auth', 'access:s0'], 'excludes middleware', null, ['auth']],
    'a Fortify two-factor route' => [['access:none,two_factor_challenge'], 'a Fortify two-factor route', 'two-factor.login'],
]);
