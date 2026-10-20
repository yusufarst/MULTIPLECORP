<?php

use PHPUnit\Framework\AssertionFailedError;
use Tests\Architecture\Rules;
use Tests\Architecture\SourceScan;

// ARCHITECTURE §19's prohibited patterns, WS-01 and PF-03: each holds on the application and is
// detected on its fixture under tests/Architecture/Fixtures.

function applicationSources(): array
{
    return [app_path(), database_path(), base_path('routes')];
}

it('finds no prohibited pattern in the application', function (string $rule) {
    expect(SourceScan::violations($rule, applicationSources()))->toBe([]);
})->with(array_keys(SourceScan::RULES));

it('detects each prohibited pattern on its fixture', function (string $rule) {
    expect(SourceScan::violations($rule, [base_path('tests/Architecture/Fixtures/Scan')]))->not->toBe([]);
})->with(array_keys(SourceScan::RULES));

it('has no repository class per model', function () {
    expect('App')->classes()->not->toHaveSuffix('Repository');
});

it('detects a repository class on its fixture', function () {
    expect(function () {
        expect('Tests\Architecture\Fixtures\App\Modules\CAT\Data')->classes()->not->toHaveSuffix('Repository');
    })->toThrow(AssertionFailedError::class);
});

it('has no catch-all Services folder', function () {
    expect(Rules::catchAllServiceFolders(app_path()))->toBe([])
        ->and(Rules::catchAllServiceFolders(base_path('tests/Architecture/Fixtures/App')))->not->toBe([]);
});
