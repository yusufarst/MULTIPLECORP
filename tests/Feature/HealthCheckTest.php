<?php

use App\Support\HealthProbe;
use Illuminate\Http\Request;

// TL-04 (TASK-072; SECURITY EN-03, SX-03; PRQ-03): ROUTE-277's and INT-277's minimal health check at its contract's path
// answers without a session, a cookie or an internal detail — 200 when the application answers, 503 with the same
// minimal answer when it cannot — and declares EN-03's `health` exception, which the route audit (SM-02) reads.

it('answers the health check with OK, starting no session and setting no cookie (INT-277)', function () {
    $response = $this->get('/kesehatan');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('OK');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->getCookies())->toBe([]);
});

it('answers 503 with the same minimal answer when the application cannot answer (INT-277)', function () {
    $this->app->instance(HealthProbe::class, new class extends HealthProbe
    {
        public function answers(): bool
        {
            return false;
        }
    });

    $response = $this->get('/kesehatan');

    $response->assertStatus(503)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toBe('UNAVAILABLE')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->getCookies())->toBe([]);
});

it('reaches the database through the probe when it answers', function () {
    expect(app(HealthProbe::class)->answers())->toBeTrue();
});

it('answers through the maintenance mode while every other request is refused (H9-14)', function () {
    // The maintenance mode on this process's array store, never the file other test processes would read.
    config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
    app()->maintenanceMode()->activate(['secret' => null, 'status' => 503]);

    try {
        $this->get('/kesehatan')->assertOk()->assertSee('OK');
        $this->get('/masuk')->assertStatus(503);
        $this->post('/masuk', [])->assertStatus(503);
    } finally {
        app()->maintenanceMode()->deactivate();
    }
});

it('declares EN-03\'s health exception at ROUTE-277\'s path and nothing else', function () {
    $route = app('router')->getRoutes()->getByName('health');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('kesehatan')
        ->and($route->gatherMiddleware())->toBe(['access:none,health'])
        ->and(app('router')->getRoutes()->match(Request::create('/up'))->isFallback)->toBeTrue();
});
