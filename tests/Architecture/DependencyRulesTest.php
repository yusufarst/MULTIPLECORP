<?php

use PHPUnit\Framework\AssertionFailedError;
use Tests\Architecture\Rules;

// SECURITY_TEST_MATRIX SM-03 and ARCHITECTURE §2–§4: each rule holds on the application and fails on
// its fixture, a deliberate violation under tests/Architecture/Fixtures.

const FIXTURES = 'Tests\Architecture\Fixtures\App';

it('keeps the layers pointing downward', function () {
    Rules::check(Rules::layers('App'));
});

it('keeps the module tiers acyclic, writing only through lower tiers', function () {
    Rules::check(Rules::tiers('App'));
});

it('keeps controllers thin', function () {
    Rules::check(Rules::controllers('App'));
});

it('fails on a domain class that uses HTTP', function () {
    expect(fn () => Rules::check(Rules::layers(FIXTURES), FIXTURES.'\Modules\INV\Models'))
        ->toThrow(AssertionFailedError::class);
});

it('fails on an action that depends on a controller', function () {
    expect(fn () => Rules::check(Rules::layers(FIXTURES), FIXTURES.'\Modules\PRJ\Actions'))
        ->toThrow(AssertionFailedError::class);
});

it('fails on a lower tier writing through a higher one', function () {
    expect(fn () => Rules::check(Rules::tiers(FIXTURES), FIXTURES.'\Modules\ORG'))
        ->toThrow(AssertionFailedError::class);
});

it('fails on an action writing another module\'s model', function () {
    expect(fn () => Rules::check(Rules::tiers(FIXTURES), FIXTURES.'\Modules\FIN'))
        ->toThrow(AssertionFailedError::class);
});

it('fails on a query reading another module\'s model instead of its query class', function () {
    expect(fn () => Rules::check(Rules::tiers(FIXTURES), FIXTURES.'\Modules\PRJ'))
        ->toThrow(AssertionFailedError::class);
});

it('accepts a relationship between two modules\' models', function () {
    Rules::check(Rules::tiers(FIXTURES), FIXTURES.'\Modules\FUL');
});

it('fails on Reporting writing through an action', function () {
    expect(fn () => Rules::check(Rules::tiers(FIXTURES), FIXTURES.'\Modules\Reporting'))
        ->toThrow(AssertionFailedError::class);
});

it('fails on a controller with business logic', function () {
    expect(fn () => Rules::check(Rules::controllers(FIXTURES), FIXTURES.'\Http\Controllers'))
        ->toThrow(AssertionFailedError::class);
});

it('derives the tiers of ARCHITECTURE §3', function () {
    expect(Rules::mayWrite('PRJ', 'ORG'))->toBeTrue()
        ->and(Rules::mayWrite('ORG', 'PRJ'))->toBeFalse()
        ->and(Rules::mayWrite('DOC', 'ORG'))->toBeTrue()
        ->and(Rules::mayWrite('ORG', 'DOC'))->toBeFalse()
        ->and(Rules::mayWrite('OPS', 'IAM'))->toBeTrue()
        ->and(Rules::mayWrite('FUL', 'FIN'))->toBeFalse()
        ->and(Rules::mayWrite('ImportOrchestration', 'FIN'))->toBeTrue();
});

it('covers every module namespace of app/Modules', function () {
    $folders = array_map('basename', glob(app_path('Modules/*'), GLOB_ONLYDIR) ?: []);
    sort($folders);

    $modules = Rules::modules();
    sort($modules);

    expect($folders)->toBe($modules);
});
