<?php

use App\Modules\PUR\Services\PurchaseTaxes;
use App\Support\Command\Refused;
use App\Support\Costing\ChargeAllocator;
use App\Support\Money;

// TL-03 (TASK-037; C-09; BR-FIN-17; DIR-024 D-1; numeric annex 14): the tax treatment chosen for its type, only the non-creditable part offered to cost, and the basis values handed to `ChargeAllocator`.

it('offers only INPUT_TO_ACQUISITION_COST\'s part to cost and snapshots each component with its rule (DIR-024 D-1)', function () {
    $taxes = new PurchaseTaxes;
    $result = $taxes->components(
        [['type' => 1, 'rate' => '11', 'amount' => '110000'], ['type' => 2, 'rate' => '2.5', 'amount' => '25000']],
        [1 => ['id' => 7, 'treatment' => 'INPUT_CREDITABLE'], 2 => ['id' => 8, 'treatment' => PurchaseTaxes::TO_COST]],
        Money::of('1000000'),
    );

    expect($result['noncreditable']->toString())->toBe('25000.00')
        ->and($result['rows'][0])->toMatchArray(['tax_type_id' => 1, 'treatment_rule_id' => 7, 'treatment' => 'INPUT_CREDITABLE', 'rate_percent' => '11', 'base_amount' => '1000000.00', 'amount' => '110000.00'])
        ->and($result['rows'][1]['treatment'])->toBe(PurchaseTaxes::TO_COST);
});

it('refuses a tax type with no treatment for the company and date (BR-FIN-17)', function () {
    expect(fn () => (new PurchaseTaxes)->components([['type' => 3, 'rate' => '1', 'amount' => '1']], [3 => null], Money::of('100')))->toThrow(Refused::class);
});

it('takes the stated tax as the truth, no formula (FS-13)', function () {
    $result = (new PurchaseTaxes)->components([['type' => 1, 'rate' => '11', 'amount' => '12345.67']], [1 => ['id' => 1, 'treatment' => PurchaseTaxes::TO_COST]], Money::of('1000'));

    expect($result['noncreditable']->toString())->toBe('12345.67');
});

it('splits annex 14\'s freight 100,000 by line value into 25,000 and 75,000, the residual on the last line', function () {
    $shares = ChargeAllocator::shares(Money::of('100000'), [11 => '1000000.00', 12 => '3000000.00']);

    expect($shares[11]->toString())->toBe('25000.00')->and($shares[12]->toString())->toBe('75000.00');

    $residual = ChargeAllocator::shares(Money::of('100'), [1 => '1', 2 => '1', 3 => '1']);
    expect($residual[1]->toString())->toBe('33.00')->and($residual[2]->toString())->toBe('33.00')->and($residual[3]->toString())->toBe('34.00');
});
