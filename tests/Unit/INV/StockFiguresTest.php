<?php

use App\Modules\INV\Data\StockFigures;
use App\Modules\INV\Services\MovementReversal;

// TL-03 (TASK-040; CALC-01; annex 7; DATABASE §5.3): AVAILABLE and the alternate-unit hint from base quantities; which movement each reversal path undoes.

it('reads annex 7: 10, 3 and 2 leave 5 available (CALC-01; BR-INV-01)', function () {
    expect(StockFigures::of('10.000', '3.000', '2.000', 'PCS'))->toBe(['physical' => '10', 'reserved' => '3', 'unusable' => '2', 'available' => '5', 'unit' => 'PCS']);
});

it('hints the alternate unit from base quantities, down and never up (PT-17)', function () {
    $figures = StockFigures::of('14.000', '12.000', '0.000', 'PCS', 'DUS', 12);

    expect($figures['alternate'])->toBe(['physical' => '1.166', 'reserved' => '1', 'unusable' => '0', 'available' => '0.166', 'unit' => 'DUS'])
        ->and(StockFigures::of('14', '0', '0', 'PCS', 'PCS', 1))->not->toHaveKey('alternate');
});

it('keeps a whole number and a fraction plain', function () {
    expect(StockFigures::plain('10.000'))->toBe('10')->and(StockFigures::plain('1.500'))->toBe('1.5')->and(StockFigures::plain('100'))->toBe('100')->and(StockFigures::plain('0.000'))->toBe('0');
});

it('undoes a consumption-creating movement only by restorations, the rest by its inverse; a receipt has its own route (DATABASE §5.3; C-27)', function () {
    foreach (['CONDITION_CHANGE', 'SURPLUS_ATTRIBUTION', 'UNATTRIBUTED_RECOGNITION', 'UNATTRIBUTED_FOUND', 'UNATTRIBUTED_CONVERSION'] as $type) {
        expect(in_array($type, MovementReversal::BY_INVERSE, true))->toBeTrue()->and(in_array($type, MovementReversal::BY_RESTORATION, true))->toBeFalse();
    }

    foreach (['ADJUSTMENT_DECREASE', 'LOSS', 'COUNT_VARIANCE'] as $type) {
        expect(in_array($type, MovementReversal::BY_RESTORATION, true))->toBeTrue()->and(in_array($type, MovementReversal::BY_INVERSE, true))->toBeFalse();
    }

    foreach (['RECEIPT', 'DISPATCH', 'OPENING', 'RESTORATION', 'LOCATION_MOVE'] as $type) {
        expect(MovementReversal::reversible($type))->toBeFalse();
    }
});
