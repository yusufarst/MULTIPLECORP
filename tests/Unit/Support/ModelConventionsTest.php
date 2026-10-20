<?php

use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\AuditEvent;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportBatch;
use App\Modules\OPS\Models\LegacyArchiveItem;
use App\Modules\OPS\Models\SecurityEvent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// TL-03 (TASK-005): the casts and value conventions of DATABASE §16–§17 on the IAM and OPS models —
// UUIDv7 public identifiers beside the internal key, money as exact decimal strings never a float,
// a one-way password hash, and append-only models that refuse a change (C-53).

it('generates a UUIDv7 public identifier beside the internal key', function (string $model) {
    $instance = new $model;

    expect($instance->uniqueIds())->toBe(['public_id'])
        ->and(Str::isUuid($instance->newUniqueId(), 7))->toBeTrue()
        ->and($instance->getKeyName())->toBe('id')
        ->and($instance->getIncrementing())->toBeTrue();
})->with([User::class, CorrectionCase::class, ImportBatch::class]);

it('carries money as an exact decimal string with two places', function (string $given, string $carried) {
    $case = new CorrectionCase;
    $case->returned_cost = $given;

    expect($case->returned_cost)->toBe($carried)->toBeString();
})->with([
    'whole rupiah' => ['1500000', '1500000.00'],
    'sen' => ['0.5', '0.50'],
    'beyond a float\'s exact range' => ['9999999999999999.99', '9999999999999999.99'],
]);

it('stores the password only as a one-way hash and never serializes a credential', function () {
    $user = new User;
    $user->password = 'kata-sandi-contoh';
    $user->two_factor_secret = 'eyJpdiI6ImNvbnRvaCJ9';

    expect($user->password)->not->toBe('kata-sandi-contoh')
        ->and(Hash::check('kata-sandi-contoh', $user->password))->toBeTrue()
        ->and($user->toArray())->not->toHaveKeys(['password', 'two_factor_secret', 'two_factor_recovery_codes'])
        ->and($user->getRememberTokenName())->toBe('');
});

it('refuses to update or delete a saved append-only row at the model (C-53)', function (string $model) {
    $row = (new $model)->forceFill(['id' => 1]);
    $row->exists = true;
    $row->syncOriginal();

    expect(fn () => $row->forceFill(['recorded_at' => now()->subYear()])->save())->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $row->delete())->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $row->increment('id'))->toThrow(LogicException::class, 'append-only');
})->with([AuditEvent::class, SecurityEvent::class, LegacyArchiveItem::class]);
