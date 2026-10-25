<?php

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

// Run inside the Docker Compose stack of DEVELOPMENT.md, which sets these variables.
function stackSetting(string $name): string
{
    $value = getenv($name);

    if (! is_string($value) || $value === '') {
        throw new RuntimeException("{$name} is not set: run this suite inside the Docker Compose stack.");
    }

    return $value;
}

function stackHttp(): PendingRequest
{
    return Http::withOptions([
        'verify' => stackSetting('STACK_CA_FILE'),
        'allow_redirects' => false,
        'http_errors' => false,
    ]);
}

it('reaches PostgreSQL', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::scalar('show server_version'))->toStartWith('18.6');
});

it('reaches Valkey for the cache', function () {
    expect((string) Redis::connection()->command('ping'))->toBe('PONG')
        ->and(Redis::connection()->command('info', ['server'])['Server']['valkey_version'] ?? null)->toBe('9.1.2');

    Cache::store('redis')->put('stack-check', 'ok', 10);

    expect(Cache::store('redis')->pull('stack-check'))->toBe('ok');
});

it('serves the application through Nginx with every WS-08 header once', function () {
    // A guest's address goes to sign-in (TASK-016's fallback, EN-03); the redirect carries the headers too.
    $response = stackHttp()->get(stackSetting('STACK_HTTPS_URL').'/no-such-page');

    expect($response->status())->toBe(302)
        ->and($response->header('Location'))->toEndWith('/masuk');

    $headers = array_change_key_case($response->headers(), CASE_LOWER);
    $expected = [
        'x-frame-options' => 'DENY',
        'x-content-type-options' => 'nosniff',
        'referrer-policy' => 'same-origin',
        'permissions-policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), bluetooth=()',
        'cross-origin-opener-policy' => 'same-origin',
        'cross-origin-resource-policy' => 'same-origin',
    ];

    foreach ($expected as $name => $value) {
        expect($headers[$name] ?? [])->toBe([$value]);
    }

    expect($headers['content-security-policy'] ?? [])->toHaveCount(1)
        ->and($headers['content-security-policy'][0])->toMatch("/^default-src 'self'; script-src 'self' 'nonce-[A-Za-z0-9]{40}'; /")
        ->and($headers['cache-control'][0] ?? '')->toContain('no-store')
        ->and($headers['server'] ?? [])->toBe(['nginx'])
        ->and($headers)->not->toHaveKey('x-powered-by')
        ->and($headers)->not->toHaveKey('strict-transport-security');
});

it('redirects plain HTTP to HTTPS', function () {
    $response = stackHttp()->get(stackSetting('STACK_HTTP_URL').'/some/path?x=1');

    $host = parse_url(stackSetting('STACK_HTTPS_URL'), PHP_URL_HOST);

    expect($response->status())->toBe(301)
        ->and($response->header('Location'))->toBe("https://{$host}:8443/some/path?x=1");
});
