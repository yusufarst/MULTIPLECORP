<?php

use App\Modules\IAM\Authentication\Totp;
use Tests\Support\TwoFactor;

// TL-03 (TASK-018; AU-16, AU-19): the period, the window and the last-step rule with a controlled clock — six
// digits, a 30-second period, one step either side and only a step later than the last accepted one —, set by the
// project rather than left to the library's defaults; the secret's length and the recovery codes' format.

beforeEach(function () {
    $this->travelTo(now()->setTimestamp(1_800_000_000));
    $this->totp = app(Totp::class);
});

it('accepts a code of now\'s step and of one step either side, never two steps away (AU-16)', function () {
    $now = Totp::currentStep();

    expect($this->totp->stepOf(TwoFactor::SECRET, TwoFactor::code(offset: 0), null))->toBe($now)
        ->and($this->totp->stepOf(TwoFactor::SECRET, TwoFactor::code(offset: -1), null))->toBe($now - 1)
        ->and($this->totp->stepOf(TwoFactor::SECRET, TwoFactor::code(offset: 1), null))->toBe($now + 1)
        ->and($this->totp->stepOf(TwoFactor::SECRET, TwoFactor::code(offset: -2), null))->toBeNull()
        ->and($this->totp->stepOf(TwoFactor::SECRET, TwoFactor::code(offset: 2), null))->toBeNull();
});

it('accepts a code only at a step later than the last accepted one (AU-16; H6-13)', function () {
    $now = Totp::currentStep();

    expect($this->totp->stepOf(TwoFactor::SECRET, TwoFactor::code(), $now))->toBeNull()
        ->and($this->totp->stepOf(TwoFactor::SECRET, TwoFactor::code(offset: -1), $now - 1))->toBeNull()
        ->and($this->totp->stepOf(TwoFactor::SECRET, TwoFactor::code(), $now - 1))->toBe($now)
        ->and($this->totp->stepOf(TwoFactor::SECRET, TwoFactor::code(offset: 1), $now))->toBe($now + 1);
});

it('moves with the clock: thirty seconds on, the code of the old step is a step behind (AU-16)', function () {
    $code = TwoFactor::code();
    $step = Totp::currentStep();

    $this->travel(Totp::PERIOD)->seconds();
    expect($this->totp->stepOf(TwoFactor::SECRET, $code, null))->toBe($step);

    $this->travel(Totp::PERIOD)->seconds();
    expect($this->totp->stepOf(TwoFactor::SECRET, $code, null))->toBeNull();
});

it('refuses what is not six digits, and a malformed secret, without an exception (AU-16)', function () {
    expect($this->totp->stepOf(TwoFactor::SECRET, '12345', null))->toBeNull()
        ->and($this->totp->stepOf(TwoFactor::SECRET, 'abcdef', null))->toBeNull()
        ->and($this->totp->stepOf('not-base32', '123456', null))->toBeNull();
});

it('makes a 32-character base32 secret and eight recovery codes in Fortify\'s format (AU-16, AU-19)', function () {
    $codes = $this->totp->newRecoveryCodes();

    expect($this->totp->newSecret())->toMatch('/^[A-Z2-7]{32}$/')
        ->and($codes)->toHaveCount(8)
        ->and(array_unique($codes))->toHaveCount(8);

    foreach ($codes as $code) {
        expect($code)->toMatch('/^[A-Za-z0-9]{10}-[A-Za-z0-9]{10}$/');
    }
});

it('encodes the QR code here, as rows of modules (AU-16)', function () {
    $rows = $this->totp->qrModules('laras@contoh.test', TwoFactor::SECRET);

    expect($rows)->not->toBeEmpty()
        ->and(strlen($rows[0]))->toBe(count($rows))
        ->and(implode('', $rows))->toMatch('/^[01]+$/');
});
