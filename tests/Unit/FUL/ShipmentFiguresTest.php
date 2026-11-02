<?php

use App\Modules\FUL\Data\ShipmentFigures;
use Brick\Math\BigDecimal;

// TL-03 (TASK-045; PX-08; C-30; C-09): a shipment line's net delivered, what closures hold back and what can still be delivered, pure; an entry at the shipment's alternate unit converted at its snapshot.

it('nets deliveries against their exact reversals (PX-08; CM-08)', function () {
    expect((string) ShipmentFigures::delivered([['qty' => '2', 'reversal' => false], ['qty' => '3', 'reversal' => false], ['qty' => '3', 'reversal' => true], ['qty' => '2', 'reversal' => false]]))->toBe('4');
});

it('holds back what a refusal closed until the restoration brings it back, never below zero (SF-RESTORE; NX-15)', function () {
    expect((string) ShipmentFigures::held(BigDecimal::of('3'), BigDecimal::zero()))->toBe('3')
        ->and((string) ShipmentFigures::held(BigDecimal::of('3'), BigDecimal::of('1')))->toBe('2')
        ->and((string) ShipmentFigures::held(BigDecimal::of('3'), BigDecimal::of('5')))->toBe('0');
});

it('leaves of an order of 5 shipped and 2 delivered 3 to deliver, less what is closed (AC-07; criterion 1)', function () {
    expect((string) ShipmentFigures::remainder(BigDecimal::of('5'), BigDecimal::of('2'), BigDecimal::zero()))->toBe('3')
        ->and((string) ShipmentFigures::remainder(BigDecimal::of('5'), BigDecimal::of('2'), BigDecimal::of('3')))->toBe('0')
        ->and((string) ShipmentFigures::remainder(BigDecimal::of('2'), BigDecimal::of('2'), BigDecimal::of('3')))->toBe('0');
});

it('converts a box of 12 at its snapshot and refuses a quantity not whole in the scale (BR-INV-03; C-09)', function () {
    expect((string) ShipmentFigures::base('2', 12, 0))->toBe('24')
        ->and((string) ShipmentFigures::base('0.5', 12, 0))->toBe('6.0')
        ->and(ShipmentFigures::base('0.25', 2, 0))->toBeNull()
        ->and((string) ShipmentFigures::base('1.5', 1, 3))->toBe('1.5')
        ->and(ShipmentFigures::base('0', 1, 0))->toBeNull();
});
