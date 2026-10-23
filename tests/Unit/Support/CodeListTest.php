<?php

use App\Modules\DOC\Models\DocumentType;
use App\Support\Database\CodeList;

// TL-03 (TASK-006): a PostgreSQL text[] of enumeration codes — a document type's allowed modes — travels
// as a list of strings (DATABASE §1, §17).

it('reads a PostgreSQL array of codes as a list of strings', function (?string $stored, ?array $list) {
    expect((new CodeList)->get(new DocumentType, 'allowed_modes', $stored, []))->toBe($list);
})->with([
    'several' => ['{RECEIPT,PRE_PAYMENT}', ['RECEIPT', 'PRE_PAYMENT']],
    'a code that begins as NULL does' => ['{NULLABLE,NULL_X}', ['NULLABLE', 'NULL_X']],
    'one' => ['{STANDARD}', ['STANDARD']],
    'none' => ['{}', []],
    'empty' => [null, null],
]);

it('writes a list of codes as a PostgreSQL array', function () {
    $type = new DocumentType;
    $type->allowed_modes = ['RECEIPT', 'PRE_PAYMENT'];

    expect($type->getAttributes()['allowed_modes'])->toBe('{RECEIPT,PRE_PAYMENT}')
        ->and($type->allowed_modes)->toBe(['RECEIPT', 'PRE_PAYMENT']);
});

it('refuses anything but a list of codes', function (mixed $value) {
    expect(fn () => (new CodeList)->set(new DocumentType, 'allowed_modes', $value, []))->toThrow(InvalidArgumentException::class);
})->with([
    'a lowercase code' => [['receipt']],
    'a code that would need quoting' => [['PRE PAYMENT']],
    'an element that would split' => [['A,B']],
    'NULL, which the array reads as a null element' => [['NULL']],
    'a keyed array' => [['mode' => 'RECEIPT']],
    'a string' => ['{RECEIPT}'],
]);

it('refuses a stored value that is not an array of codes', function (string $stored) {
    expect(fn () => (new CodeList)->get(new DocumentType, 'allowed_modes', $stored, []))->toThrow(InvalidArgumentException::class);
})->with(['{"RECEIPT"}', '{receipt}', 'RECEIPT', '{RECEIPT,}', '{NULL,RECEIPT}']);
