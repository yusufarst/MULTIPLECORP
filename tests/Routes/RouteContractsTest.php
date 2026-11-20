<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Tests\Support\RouteContracts;

// TL-07 (TASK-069; TEST_STRATEGY §8; SM-02; GAP-035; criterion 6): every registered route and every ROUTE- row of
// ROUTE_CONTRACTS one to one — a route added without a contract, or a contract left without its route, fails.

it('holds every registered route to its ROUTE- row and every row to its route (GAP-035)', function () {
    $rows = RouteContracts::rows();

    expect($rows)->toHaveCount(220)
        ->and(array_unique(array_column($rows, 'id')))->toHaveCount(220)
        ->and(RouteContracts::differences(Route::getRoutes()->getRoutes()))->toBe([]);
});

it('finds a route added without a contract', function () {
    $routes = [...Route::getRoutes()->getRoutes(), new RouteDefinition(['POST'], 'proyek/{id}/teleportasi', fn () => '')];

    expect(RouteContracts::differences($routes))->toBe(['POST proyek/{}/teleportasi: a registered route without a contract']);
});

it('finds a contract left without its route', function () {
    $routes = array_values(array_filter(Route::getRoutes()->getRoutes(), fn (RouteDefinition $route): bool => $route->uri() !== 'gudang/pindai'));

    expect(RouteContracts::differences($routes))->toBe(['ROUTE-282: no registered route answers POST gudang/pindai']);
});

it('keeps the staging-only CSP report absent from every other environment (ROUTE-278; WS-08)', function () {
    $routes = [...Route::getRoutes()->getRoutes(), new RouteDefinition(['POST'], 'laporan-csp', fn () => '')];

    expect(collect(RouteContracts::rows())->firstWhere('id', 'ROUTE-278'))->toMatchArray(['method' => 'POST', 'paths' => ['laporan-csp'], 'staging' => true])
        ->and(RouteContracts::differences($routes))->toBe(['ROUTE-278: POST laporan-csp is registered outside staging']);
});
