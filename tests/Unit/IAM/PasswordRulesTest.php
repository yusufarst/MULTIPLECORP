<?php

use App\Modules\IAM\Authentication\AttemptLogin;
use App\Modules\IAM\Authentication\CommonPasswords;
use App\Modules\IAM\Authentication\LoginThrottle;

// TL-03 (TASK-017; SM-16): AU-03's local list of common passwords, checked with no network call, and the
// normalization every check, hash and comparison uses (NFC; AU-01's e-mail); AU-05's address key, an IPv6 address
// counted by its /64.

it('finds a common password and its decorated forms, case-insensitively (AU-03)', function (string $password, bool $common) {
    expect(CommonPasswords::contains($password))->toBe($common);
})->with([
    ['password', true],
    ['Password1!', true],
    ['P@ssw0rd', true],
    ['Bismillah2026!', true],
    ['Indonesia45!!', true],
    ['Rapat-Gudang-24!', false],
    ['Kursi Biru Lantai 3!', false],
]);

it('normalizes a password to NFC and an e-mail to trimmed lower case (AU-01, AU-03)', function () {
    expect(AttemptLogin::password("Cafe\u{0301}-Kopi-9"))->toBe("Caf\u{00E9}-Kopi-9")
        ->and(AttemptLogin::password(null))->toBe('')
        ->and(AttemptLogin::identifier('  Laras@Contoh.TEST '))->toBe('laras@contoh.test');
});

it('counts an IPv6 address by its /64 and an IPv4 address whole (AU-05)', function () {
    expect(LoginThrottle::addressKey('2001:db8:aa:bb:1::1'))->toBe(LoginThrottle::addressKey('2001:db8:aa:bb:ffff:ffff:ffff:ffff'))
        ->and(LoginThrottle::addressKey('2001:db8:aa:bc::1'))->not->toBe(LoginThrottle::addressKey('2001:db8:aa:bb::1'))
        ->and(LoginThrottle::addressKey('10.0.0.9'))->toBe('10.0.0.9')
        ->and(LoginThrottle::addressKey(null))->toBe('none');
});
