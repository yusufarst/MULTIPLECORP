<?php

use App\Modules\IAM\Authentication\GoogleIdToken;

// TL-03 (TASK-019; SECURITY AU-22): the checks of the ID token Google's token endpoint returns — its nonce, its
// audience, its issuer in either form and its expiry —, the subject and the e-mail taken from it alone.

function idToken(array $claims): string
{
    $encode = fn (array $part): string => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');

    return $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims).'.signature';
}

function claims(array $overrides = []): array
{
    return [...['iss' => 'https://accounts.google.com', 'aud' => 'klien-uji', 'sub' => '110248495921238986420', 'email' => 'laras@contoh.test', 'email_verified' => true, 'nonce' => 'nonce-uji', 'exp' => 1_800_000_600], ...$overrides];
}

it('takes the subject and the e-mail from a token whose nonce, audience, issuer and expiry hold (AU-22)', function () {
    expect(GoogleIdToken::verified(idToken(claims()), 'klien-uji', 'nonce-uji', 1_800_000_000))->toBe(['sub' => '110248495921238986420', 'email' => 'laras@contoh.test'])
        ->and(GoogleIdToken::verified(idToken(claims(['iss' => 'accounts.google.com'])), 'klien-uji', 'nonce-uji', 1_800_000_000))->not->toBeNull()
        ->and(GoogleIdToken::verified(idToken(claims(['aud' => ['lain', 'klien-uji'], 'azp' => 'klien-uji'])), 'klien-uji', 'nonce-uji', 1_800_000_000))->not->toBeNull()
        ->and(GoogleIdToken::verified(idToken(claims(['email_verified' => false])), 'klien-uji', 'nonce-uji', 1_800_000_000))->toBe(['sub' => '110248495921238986420', 'email' => null]);
});

it('refuses a token whose nonce, audience, issuer, expiry or subject does not hold (AU-22)', function (array $overrides) {
    expect(GoogleIdToken::verified(idToken(claims($overrides)), 'klien-uji', 'nonce-uji', 1_800_000_000))->toBeNull();
})->with([
    'another nonce' => [['nonce' => 'nonce-lain']],
    'no nonce' => [['nonce' => null]],
    'another audience' => [['aud' => 'klien-lain']],
    'several audiences, issued to another client' => [['aud' => ['lain', 'klien-uji'], 'azp' => 'lain']],
    'another issuer' => [['iss' => 'https://evil.example']],
    'expired' => [['exp' => 1_800_000_000]],
    'no expiry' => [['exp' => null]],
    'no subject' => [['sub' => null]],
    'a subject outside printable ASCII' => [['sub' => "abc\u{00e9}"]],
]);

it('refuses what is not a token, and an empty client or nonce (AU-22)', function () {
    expect(GoogleIdToken::verified(null, 'klien-uji', 'nonce-uji', 1_800_000_000))->toBeNull()
        ->and(GoogleIdToken::verified('bukan.token', 'klien-uji', 'nonce-uji', 1_800_000_000))->toBeNull()
        ->and(GoogleIdToken::verified('a.!!!.c', 'klien-uji', 'nonce-uji', 1_800_000_000))->toBeNull()
        ->and(GoogleIdToken::verified(idToken(claims()), '', 'nonce-uji', 1_800_000_000))->toBeNull()
        ->and(GoogleIdToken::verified(idToken(claims()), 'klien-uji', '', 1_800_000_000))->toBeNull();
});
