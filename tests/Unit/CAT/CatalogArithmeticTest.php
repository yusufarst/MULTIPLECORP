<?php

use App\Modules\CAT\Data\CodeKey;
use App\Modules\CAT\Data\Quantity;

// TL-03 (TASK-027; BR-INV-09; C-01; C-09; L-31; numeric annex 10–11): a code's key — white space removed, letters
// upper-cased, leading zeros kept —; an entered quantity converted to the base by its integer ratio only, exactly; a
// base quantity storable only at its product's scale.

it('normalizes a code however it is typed or scanned, keeping its leading zeros (criterion 1; C-01)', function (string $typed, string $key) {
    expect(CodeKey::of($typed))->toBe($key);
})->with([
    ['0089 9123 4567', '008991234567'],
    [" mc0000\t0001 ", 'MC00000001'],
    ['abc-12', 'ABC-12'],
]);

it('converts by multiplication by the integer ratio only, exactly (criterion 3; numeric annex 10–11)', function (string $entered, int $ratio, string $base) {
    expect(Quantity::toBase($entered, $ratio))->toBe($base);
})->with([
    'a box of twelve' => ['1', 12, '12.000'],
    'two boxes at ratio 12 pin 24 pieces' => ['2', 12, '24.000'],
    'the same entry at a later ratio of 10' => ['2', 10, '20.000'],
    'a measured quantity' => ['0.5', 3, '1.500'],
    'nothing' => ['0', 7, '0.000'],
    'large values without floats' => ['999999999999999.999', 1000, '999999999999999999.000'],
]);

it('refuses a ratio below one and a quantity of more than three places (C-09)', function () {
    expect(fn () => Quantity::toBase('1', 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Quantity::toBase('1.2345', 2))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Quantity::toBase('-1', 2))->toThrow(InvalidArgumentException::class);
});

it('stores a base quantity only at its product\'s scale — no fractional piece of a counted product (criterion 9)', function () {
    expect(Quantity::atScale('24.000', 0))->toBeTrue()
        ->and(Quantity::atScale('1.500', 0))->toBeFalse()
        ->and(Quantity::atScale('1.500', 1))->toBeTrue()
        ->and(Quantity::atScale('1.505', 2))->toBeFalse()
        ->and(Quantity::atScale('1.505', 3))->toBeTrue();
});
