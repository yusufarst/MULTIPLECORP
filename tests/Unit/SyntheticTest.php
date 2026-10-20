<?php

use Tests\Support\Synthetic;

// TEST_STRATEGY TS-04: synthetic values from a fixed seed, invented and unable to reach anyone.

it('gives the same values for the same seed and others for another seed', function () {
    $sequence = function (int $seed): array {
        $synthetic = new Synthetic($seed);

        return [$synthetic->companyName(), $synthetic->personName(), $synthetic->phone(), $synthetic->taxNumber(), $synthetic->bankAccount(), $synthetic->address(), $synthetic->amount(1_000, 9_000_000)];
    };

    expect($sequence(Synthetic::SEED))->toBe($sequence(Synthetic::SEED))
        ->and($sequence(Synthetic::SEED + 1))->not->toBe($sequence(Synthetic::SEED));
});

it('invents values that no real person or company can hold', function () {
    $synthetic = new Synthetic;

    foreach (range(1, 200) as $index) {
        expect($synthetic->companyName())->toStartWith('PT Contoh ')
            ->and($synthetic->email("user-{$index}"))->toMatch('/^user-\d+@example\.(com|net|org)$/')
            ->and($synthetic->phone())->toMatch('/^\+62 000 \d{4} \d{4}$/')
            ->and($synthetic->taxNumber())->toMatch('/^00\d{14}$/')
            ->and($synthetic->bankAccount())->toMatch('/^0000\d{6}$/')
            ->and($synthetic->address())->toContain('Contoh')
            ->and($synthetic->amount(1, 10))->toMatch('/^([1-9]|10)\.00$/');
    }
});
