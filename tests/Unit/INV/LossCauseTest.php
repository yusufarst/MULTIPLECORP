<?php

use App\Modules\INV\Data\LossCause;
use Brick\Math\BigDecimal;

// TL-03 (TASK-042; L-38; SF-LOSS; annex 16): the causal project of a loss, derived and never taken from a payload.

$case = ['id' => 9, 'company' => 2, 'project' => 7, 'project_company' => 2];
$receipt = ['project' => 5, 'company' => 1, 'open' => BigDecimal::of('2')];

it('charges a unit lost under a case to the case\'s causal project, whatever the condition (CM-11)', function () use ($case, $receipt) {
    $parts = LossCause::of(1, $case, $receipt, 'USABLE', BigDecimal::of('3'));

    expect($parts)->toHaveCount(1)->and($parts[0]['company'])->toBe(2)->and($parts[0]['project'])->toBe(7)->and($parts[0]['case'])->toBe(9)->and($parts[0]['case_company'])->toBe(2)->and((string) $parts[0]['qty'])->toBe('3');
});

it('charges a case with no causal project to the lot\'s owning company', function () {
    $parts = LossCause::of(1, ['id' => 9, 'company' => 1, 'project' => null, 'project_company' => null], null, 'USABLE', BigDecimal::of('1'));

    expect($parts[0]['company'])->toBe(1)->and($parts[0]['project'])->toBeNull()->and($parts[0]['case'])->toBe(9);
});

it('charges unusable units damaged at a line\'s receipt to its project, as far as that damage goes, the rest to the owning company', function () use ($receipt) {
    $parts = LossCause::of(1, null, $receipt, 'UNUSABLE', BigDecimal::of('3'));

    expect($parts)->toHaveCount(2)->and($parts[0]['project'])->toBe(5)->and((string) $parts[0]['qty'])->toBe('2')->and($parts[1]['project'])->toBeNull()->and($parts[1]['company'])->toBe(1)->and((string) $parts[1]['qty'])->toBe('1')
        ->and(LossCause::of(1, null, $receipt, 'UNUSABLE', BigDecimal::of('1'))[0]['project'])->toBe(5);
});

it('charges a case as far as it answers, then the rest by the rule', function () use ($receipt) {
    $parts = LossCause::of(1, ['id' => 9, 'company' => 2, 'project' => 7, 'project_company' => 2, 'open' => BigDecimal::of('1')], $receipt, 'UNUSABLE', BigDecimal::of('4'));

    expect($parts)->toHaveCount(3)->and($parts[0]['case'])->toBe(9)->and((string) $parts[0]['qty'])->toBe('1')->and($parts[1]['project'])->toBe(5)->and((string) $parts[1]['qty'])->toBe('2')->and($parts[2]['project'])->toBeNull()->and((string) $parts[2]['qty'])->toBe('1');
});

it('never reads a usable unit, or damage already lost, as the receipt\'s', function () use ($receipt) {
    expect(LossCause::of(1, null, $receipt, 'USABLE', BigDecimal::of('1'))[0]['project'])->toBeNull()
        ->and(LossCause::of(1, null, [...$receipt, 'open' => BigDecimal::zero()], 'UNUSABLE', BigDecimal::of('1'))[0]['project'])->toBeNull()
        ->and(LossCause::of(1, null, null, 'UNUSABLE', BigDecimal::of('1'))[0]['company'])->toBe(1);
});
