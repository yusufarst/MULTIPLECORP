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

it('declares EN-03\'s health exception and nothing else', function () {
    $route = app('router')->getRoutes()->getByName('health');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('up')
        ->and($route->gatherMiddleware())->toBe(['access:none,health']);
});
