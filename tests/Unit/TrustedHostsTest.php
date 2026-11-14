<?php

use App\Support\TrustedHosts;

// TL-03 (TASK-072; SECURITY WS-11; TM-01; TM-07): the Host header's patterns match each configured domain whole, never a
// subdomain or a look-alike, and fall back to the application URL's host — as Symfony's request applies them.

$matches = fn (array $patterns, string $host): bool => array_filter($patterns, fn (string $pattern): bool => preg_match('{'.$pattern.'}i', $host) === 1) !== [];

it('matches each configured domain whole and nothing around it', function () use ($matches) {
    $patterns = TrustedHosts::patterns([' app.example.test ', '', 'staging.example.test'], 'https://ignored.example.test');

    expect($matches($patterns, 'app.example.test'))->toBeTrue()
        ->and($matches($patterns, 'staging.example.test'))->toBeTrue()
        ->and($matches($patterns, 'evil.app.example.test'))->toBeFalse()
        ->and($matches($patterns, 'app.example.test.evil.test'))->toBeFalse()
        ->and($matches($patterns, 'appXexample.test'))->toBeFalse()
        ->and($matches($patterns, 'ignored.example.test'))->toBeFalse();
});

it('falls back to the application URL\'s host when none is configured', function () use ($matches) {
    $patterns = TrustedHosts::patterns([], 'https://app.example.test/path');

    expect($patterns)->toHaveCount(1)
        ->and($matches($patterns, 'app.example.test'))->toBeTrue()
        ->and($matches($patterns, 'other.example.test'))->toBeFalse();
});
