<?php

use Illuminate\Support\Facades\Route;

it('boots and answers a request', function () {
    $this->get('/')->assertNotFound();

    expect(app()->isBooted())->toBeTrue();
});

it('registers no route', function () {
    expect(Route::getRoutes()->getRoutes())->toBe([]);
});

it('keeps the framework default migrations out: every migration is the project\'s, named by its module', function () {
    $modules = 'adm|cat|cst|doc|fin|ful|iam|inv|ops|org|prj|pty|pur';

    foreach (glob(database_path('migrations/*.php')) ?: [] as $migration) {
        expect(basename($migration))->toMatch("/^\\d{4}_\\d{2}_\\d{2}_\\d{6}_({$modules})_[a-z0-9_]+\\.php\$/")
            ->not->toStartWith('0001_01_01_');
    }
});
