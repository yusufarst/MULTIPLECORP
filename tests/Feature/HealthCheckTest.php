<?php

// TL-04 (TASK-072; SECURITY EN-03, SX-03; PRQ-03): the minimal health check answers without a session, a cookie or an
// internal detail, and declares EN-03's `health` exception, which the route audit (SM-02) reads.

it('answers the health check with OK, starting no session and setting no cookie', function () {
    $response = $this->get('/up');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('OK');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->getCookies())->toBe([]);
});

it('answers through the maintenance mode while every other request is refused (H9-14)', function () {
    // The maintenance mode on this process's array store, never the file other test processes would read.
    config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
    app()->maintenanceMode()->activate(['secret' => null, 'status' => 503]);

    try {
        $this->get('/up')->assertOk()->assertSee('OK');
        $this->get('/masuk')->assertStatus(503);
        $this->post('/masuk', [])->assertStatus(503);
    } finally {
        app()->maintenanceMode()->deactivate();
    }
});

it('declares EN-03\'s health exception and nothing else', function () {
    $route = app('router')->getRoutes()->getByName('health');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('up')
        ->and($route->gatherMiddleware())->toBe(['access:none,health']);
});
