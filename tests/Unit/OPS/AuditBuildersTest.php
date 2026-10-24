<?php

use App\Modules\OPS\Data\AuditChange;
use App\Modules\OPS\Data\AuditLinkage;
use App\Modules\OPS\Enums\AuditRelation;
use App\Modules\OPS\Enums\AuthorityClass;
use App\Support\Redaction;
use Carbon\CarbonImmutable;

// TL-03 (TASK-010): the before/after and linkage builders of the business audit (BR-XC-01; BR-CR-01;
// DATABASE §23; SECURITY LG-01, LG-03).

it('gives a created row an after only, its keys sorted and its values JSON scalars', function () {
    $change = AuditChange::created([
        'status' => AuthorityClass::Adm,
        'issued_at' => CarbonImmutable::parse('2026-10-24T23:30:00+07:00'),
        'amount' => '1000000.00',
        'lines' => [['qty' => '2.0000']],
        'is_active' => true,
    ]);

    expect($change->before)->toBeNull()
        ->and($change->after)->toBe([
            'amount' => '1000000.00',
            'is_active' => true,
            'issued_at' => '2026-10-24T16:30:00.000000Z',
            'lines' => [['qty' => '2.0000']],
            'status' => 'ADM',
        ]);
});

it('keeps only the changed keys of an update, on both sides', function () {
    $change = AuditChange::updated(
        ['name' => 'PT Lama', 'npwp' => '01.234', 'is_active' => true, 'retired' => 'x'],
        ['name' => 'PT Baru', 'npwp' => '01.234', 'is_active' => true, 'note' => 'baru'],
    );

    expect($change->before)->toBe(['name' => 'PT Lama', 'note' => null, 'retired' => 'x'])
        ->and($change->after)->toBe(['name' => 'PT Baru', 'note' => 'baru', 'retired' => null])
        ->and($change->isEmpty())->toBeFalse();
});

it('shows a business row\'s state and code changes in the clear', function () {
    $change = AuditChange::updated(['state' => 'DRAFT', 'code' => 'PT-A'], ['state' => 'ISSUED', 'code' => 'PT-B']);

    expect($change->before)->toBe(['code' => 'PT-A', 'state' => 'DRAFT'])
        ->and($change->after)->toBe(['code' => 'PT-B', 'state' => 'ISSUED']);
});

it('compares instants by their moment, not their zone', function () {
    $change = AuditChange::updated(
        ['due_at' => CarbonImmutable::parse('2026-10-24T17:00:00Z')],
        ['due_at' => CarbonImmutable::parse('2026-10-25T00:00:00+07:00')],
    );

    expect($change->isEmpty())->toBeTrue();
});

it('shows a changed secret by its key with both values redacted, and an unchanged one not at all (LG-03)', function () {
    $changed = AuditChange::updated(['password' => 'hash-old', 'name' => 'A'], ['password' => 'hash-new', 'name' => 'A']);
    $unchanged = AuditChange::updated(['remember_token' => 't1', 'name' => 'A'], ['remember_token' => 't1', 'name' => 'B']);
    $created = AuditChange::created(['two_factor_secret' => 'JBSWY3DP', 'nested' => ['api_key' => 'k-1', 'ok' => 1]]);

    expect($changed->before)->toBe(['password' => Redaction::MARK])
        ->and($changed->after)->toBe(['password' => Redaction::MARK])
        ->and($unchanged->after)->toBe(['name' => 'B'])
        ->and($created->after)->toBe(['nested' => ['api_key' => Redaction::MARK, 'ok' => 1], 'two_factor_secret' => Redaction::MARK])
        ->and(json_encode([$changed, $unchanged, $created]))->not->toContain('hash-old', 'hash-new', 't1', 'JBSWY3DP', 'k-1');
});

it('refuses a value that is not a scalar, instant, enum or array', function (mixed $value) {
    AuditChange::created(['value' => $value]);
})->with([
    'an object' => [new stdClass],
    'a binary fraction, never money' => [0.1],
])->throws(InvalidArgumentException::class);

it('refuses an unnamed value', function () {
    AuditChange::created(['first']);
})->throws(InvalidArgumentException::class);

it('records a change with no before/after', function () {
    expect(AuditChange::none()->before)->toBeNull()->and(AuditChange::none()->after)->toBeNull();
});

it('links a correction to its original and its case, each once, by table and public id (BR-CR-01)', function () {
    $original = '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';
    $case = '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c';

    $linkage = AuditLinkage::make()
        ->link(AuditRelation::Voids, 'invoices', strtoupper($original))
        ->link(AuditRelation::Voids, 'invoices', $original)
        ->link(AuditRelation::Affects, 'invoice_balances', $case)
        ->correctionCase($case);

    expect($linkage->toArray())->toBe([
        'links' => [
            ['relation' => 'VOIDS', 'entity_type' => 'invoices', 'public_id' => $original],
            ['relation' => 'AFFECTS', 'entity_type' => 'invoice_balances', 'public_id' => $case],
        ],
        'correction_case_public_id' => $case,
    ])->and(AuditLinkage::make()->toArray())->toBe([]);
});

it('refuses a link without a table name and a public id', function (string $table, string $publicId) {
    AuditLinkage::make()->link(AuditRelation::Revises, $table, $publicId);
})->with([
    'an internal id' => ['invoices', '42'],
    'a class name' => ['App\\Invoice', '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b'],
])->throws(InvalidArgumentException::class);
