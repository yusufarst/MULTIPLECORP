<?php

use App\Modules\OPS\Models\ImportBatch;
use App\Support\Database\IntegerList;

// TL-03 (TASK-005): a PostgreSQL bigint[] — a company set — travels as a list of integers (DATABASE §17).

it('reads a PostgreSQL integer array as a list of integers', function (?string $stored, ?array $list) {
    expect((new IntegerList)->get(new ImportBatch, 'company_scope', $stored, []))->toBe($list);
})->with([
    'several' => ['{1001,1002,-3}', [1001, 1002, -3]],
    'one' => ['{1001}', [1001]],
    'none' => ['{}', []],
    'empty' => [null, null],
]);

it('writes a list of integers as a PostgreSQL integer array', function () {
    $batch = new ImportBatch;
    $batch->company_scope = [1001, 1002];

    expect($batch->getAttributes()['company_scope'])->toBe('{1001,1002}')
        ->and($batch->company_scope)->toBe([1001, 1002]);
});

it('refuses anything but integers', function (mixed $value) {
    expect(fn () => (new IntegerList)->set(new ImportBatch, 'company_scope', $value, []))->toThrow(InvalidArgumentException::class);
})->with([
    'a numeric string' => [['1001']],
    'a keyed array' => [['a' => 1001]],
    'a string' => ['{1001}'],
]);

it('refuses a stored value that is not an integer array', function (string $stored) {
    expect(fn () => (new IntegerList)->get(new ImportBatch, 'company_scope', $stored, []))->toThrow(InvalidArgumentException::class);
})->with(['{a,b}', '{1.5}', '1001', '{1001,}']);
