<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // The framework skips request-forgery checks under unit tests; this instance does not.
    $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });

    Route::middleware('web')->post('/__test/command', fn () => 'done');
    Route::middleware('web')->get('/__test/page', fn () => 'page');
});

it('refuses a state-changing request without a token', function () {
    $this->post('/__test/command')->assertStatus(419);
});

it('refuses a cross-site or same-site request without a token', function (string $site) {
    $this->post('/__test/command', [], ['Sec-Fetch-Site' => $site])->assertStatus(419);
})->with(['cross-site', 'same-site', 'none']);

it('refuses a wrong token', function () {
    $this->withSession(['_token' => 'session-token'])
        ->post('/__test/command', ['_token' => 'other-token'])
        ->assertStatus(419);
});

it('accepts the session token', function () {
    $this->withSession(['_token' => 'session-token'])
        ->post('/__test/command', ['_token' => 'session-token'])
        ->assertOk();
});

it('accepts a same-origin request', function () {
    $this->post('/__test/command', [], ['Sec-Fetch-Site' => 'same-origin'])->assertOk();
});

it('runs in the web group and excludes no path', function () {
    expect(app(Kernel::class)->getMiddlewareGroups()['web'])->toContain(PreventRequestForgery::class)
        ->and(app(PreventRequestForgery::class)->getExcludedPaths())->toBe([]);
});

it('sets the cookie flags of WS-05', function () {
    $response = $this->get('/__test/page')->assertOk();

    $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());

    $xsrf = $cookies->get('XSRF-TOKEN');
    expect($xsrf)->not->toBeNull()
        ->and($xsrf->isSecure())->toBeTrue()
        ->and($xsrf->isHttpOnly())->toBeFalse()
        ->and($xsrf->getSameSite())->toBe('lax');

    $session = $cookies->get(config('session.cookie'));
    expect($session)->not->toBeNull()
        ->and($session->isSecure())->toBeTrue()
        ->and($session->isHttpOnly())->toBeTrue()
        ->and($session->getSameSite())->toBe('lax');
});
