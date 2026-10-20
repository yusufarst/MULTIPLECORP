<?php

use Illuminate\Support\Facades\Route;

it('boots and answers a request', function () {
    $this->get('/')->assertNotFound();

    expect(app()->isBooted())->toBeTrue();
});

it('registers no route', function () {
    expect(Route::getRoutes()->getRoutes())->toBe([]);
});

it('keeps the framework default migrations out', function () {
    expect(glob(database_path('migrations/*.php')))->toBe([]);
});
