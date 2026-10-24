<?php

use App\Modules\OPS\Data\UserAgentSummary;
use App\Modules\OPS\Services\SecurityLogHash;
use App\Support\Redaction;
use Illuminate\Database\QueryException;

// TL-03 (TASK-010): the values a security event and a log line may hold (SECURITY LG-02–LG-04; D-SEC-13).

it('hashes an identifier and an address with the dedicated key, never in the clear (LG-03)', function () {
    $hash = SecurityLogHash::identifier(' Owner@Example.COM ');

    expect($hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($hash)->toBe(SecurityLogHash::identifier('owner@example.com'))
        ->and($hash)->not->toBe(hash('sha256', 'owner@example.com'))
        ->and(SecurityLogHash::address('2001:db8::1'))->toBe(SecurityLogHash::address('2001:0db8:0000:0000:0000:0000:0000:0001'))
        ->and(SecurityLogHash::address('10.0.0.1'))->not->toBe(SecurityLogHash::identifier('10.0.0.1'));

    config(['security.log_hmac_key' => 'another-testing-only-security-log-key']);

    expect(SecurityLogHash::identifier('owner@example.com'))->not->toBe($hash);
});

it('refuses a missing, short or APP_KEY hash key', function (?string $key) {
    config(['app.key' => 'base64:testing-only-application-key-value-000000=']);
    config(['security.log_hmac_key' => $key]);

    SecurityLogHash::identifier('owner@example.com');
})->with([
    'missing' => [null],
    'short' => ['too-short'],
    'APP_KEY' => ['base64:testing-only-application-key-value-000000='],
])->throws(RuntimeException::class);

it('summarizes a user agent to its browser and platform families', function (?string $agent, ?string $summary) {
    expect(UserAgentSummary::of($agent))->toBe($summary);
})->with([
    'Chrome on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', 'Chrome 129 on Windows'],
    'Edge, which also announces Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.2792.79', 'Edge 129 on Windows'],
    'Safari on iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari 18 on iOS'],
    'Firefox on Android' => ['Mozilla/5.0 (Android 14; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0', 'Firefox 131 on Android'],
    'an injected line' => ["curl/8.0\r\nFAKE LOG LINE", 'Other on Other'],
    'none' => [null, null],
]);

it('refuses a repository placeholder as the production key', function () {
    app()['env'] = 'production';
    config(['security.log_hmac_key' => 'change-me-local-only-security-log-hmac']);

    SecurityLogHash::identifier('owner@example.com');
})->throws(RuntimeException::class);

it('redacts the sensitive keys of LG-03 at any depth and leaves a business row\'s state and code', function () {
    $secrets = [
        'password' => 'p', 'password_confirmation' => 'p', 'remember_token' => 't', 'nonce' => 'n', 'Authorization' => 'Bearer x',
        'cookie' => 'c', 'XSRF-TOKEN' => 'x', 'x-api-key' => 'k', 'recovery_codes' => ['a'], 'totp_code' => '123456',
        'two_factor_code' => '654321', 'oauth_state' => 'o', 'provider_subject' => '1098', 'otp' => '1',
        'nested' => ['client_secret' => 'cs', 'signature' => 'sig'],
    ];
    $business = ['status' => 'ISSUED', 'state' => 'CONFIRMED', 'code' => 'PT-A', 'product_code' => 'SKU-1', 'session_method' => 'PASSWORD', 'stock_state' => 'GOOD', 'hotpot' => 1];

    $marked = array_map(fn (mixed $value) => is_array($value) && ! array_is_list($value) ? array_map(fn () => Redaction::MARK, $value) : Redaction::MARK, $secrets);

    expect(Redaction::redact([...$secrets, ...$business]))->toBe([...$marked, ...$business]);
});

it('also redacts the request keys an OAuth callback or a code form submits in a log line (LG-03)', function () {
    expect(Redaction::forLog(['state' => 's', 'code' => '123456', 'key' => 'k', 'status' => 'ISSUED']))
        ->toBe(['state' => Redaction::MARK, 'code' => Redaction::MARK, 'key' => Redaction::MARK, 'status' => 'ISSUED']);
});

it('logs a database failure without its bound values or the values its detail quotes (LG-03)', function () {
    $previous = new PDOException('SQLSTATE[23505]: Unique violation: DETAIL: Key (token_hash)=(a1b2c3secret) already exists.');
    $failure = new QueryException('pgsql', 'insert into credential_links (token_hash) values (?)', ['a1b2c3secret'], $previous);

    $logged = json_encode(Redaction::forLog(['exception' => $failure]));

    expect(Redaction::exceptionMessage($failure))->toBe('SQLSTATE[0] on: insert into credential_links (token_hash) values (?)')
        ->and($logged)->toContain('QueryException', 'credential_links')
        ->and($logged)->not->toContain('a1b2c3secret');
});

it('neutralizes line breaks and control characters in logged values (LG-04)', function () {
    expect(Redaction::neutralize("a\r\nb\tc\x1b[31m\u{2028}d"))->toBe('a\r\nb\tc\u{001B}[31m\u{2028}d')
        ->and(Redaction::forLog(['note' => "x\ny", 'token' => "t\n"]))->toBe(['note' => 'x\ny', 'token' => Redaction::MARK]);
});
