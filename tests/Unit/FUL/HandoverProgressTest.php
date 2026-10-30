<?php

use App\Modules\FUL\Data\HandoverProgress;

// TL-03 (TASK-047; C-09; L-39; PX-03): the remaining-to-hand-over figure in base units — the pinned demand less what is handed
// over and cancelled, never below zero —, and that figure in the line's entered unit, rounded down so the form never offers
// more than remains.

it('is the demand less what is handed over and cancelled, in base units', function (string $demand, string $handed, string $cancelled, string $remainder) {
    expect(HandoverProgress::remainder($demand, $handed, $cancelled))->toBe($remainder);
})->with([
    'nothing yet' => ['10.000', '0.000', '0.000', '10.000'],
    'partly handed over' => ['10.000', '4.000', '0.000', '6.000'],
    'partly cancelled too' => ['10.000', '4.000', '1.500', '4.500'],
    'all of it' => ['10.000', '8.000', '2.000', '0.000'],
    'never below zero' => ['10.000', '10.000', '1.000', '0.000'],
]);

it('states the remainder in the entered unit, rounded down to the product\'s scale', function (string $base, int $ratio, int $scale, string $entered) {
    expect(HandoverProgress::inEnteredUnit($base, $ratio, $scale))->toBe($entered);
})->with([
    'the base unit itself' => ['6.000', 1, 0, '6'],
    'a whole number of the larger unit' => ['24.000', 12, 0, '2'],
    'never more than remains' => ['25.000', 12, 0, '2'],
    'at the product\'s decimals' => ['10.000', 3, 3, '3.333'],
]);
