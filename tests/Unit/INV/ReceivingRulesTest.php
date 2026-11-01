<?php

use App\Modules\INV\Data\ReceiptEntry;
use App\Modules\INV\Services\ReceivingRecords;
use App\Support\Command\Refused;

// TL-03 (TASK-039; C-09; L-31; C-29; PX-03; FU-13; PJ-23; BD-18): the receipt's base quantities exact and never rounded; the cap of each receipt kind; the lot code opaque, its generation bounded.

function guardRow(array $values = []): object
{
    return (object) [...['ordered_qty_base' => '10.000', 'normal_received_net_qty_base' => '8.000', 'closed_qty_base' => '0.000', 'dropship_confirmed_net_qty_base' => '0.000', 'replacement_expected_qty_base' => '2.000', 'replacement_received_net_qty_base' => '0.000'], ...$values];
}

it('converts an entered quantity by its ratio, exactly (C-09; L-31)', function () {
    expect(ReceivingRecords::base('2', 12, 0)->isEqualTo(24))->toBeTrue()
        ->and(ReceivingRecords::base('1.5', 2, 0)->isEqualTo(3))->toBeTrue()
        ->and(ReceivingRecords::base('0.125', 1, 3)->isEqualTo('0.125'))->toBeTrue()
        ->and(ReceivingRecords::base('0', 1, 0)->isZero())->toBeTrue();
});

it('refuses a fraction of a counted product and a negative quantity, never rounding (C-09)', function (string $entered, int $ratio, int $scale) {
    expect(fn () => ReceivingRecords::base($entered, $ratio, $scale))->toThrow(Refused::class);
})->with([
    'half a counted unit' => ['0.5', 1, 0],
    'below the scale' => ['0.1234', 1, 3],
    'negative' => ['-1', 1, 3],
]);

it('keeps each kind to its own cap: a normal receipt never reaches into the replacement cap (C-29; PX-03; FX-04 scenario 5)', function () {
    expect((string) ReceivingRecords::room(ReceiptEntry::ORIGINAL, guardRow()))->toBe('2.000')
        ->and((string) ReceivingRecords::room(ReceiptEntry::SUPPLEMENT, guardRow()))->toBe('2.000')
        ->and((string) ReceivingRecords::room(ReceiptEntry::RE_RECEIPT, guardRow()))->toBe('2.000')
        ->and((string) ReceivingRecords::room(ReceiptEntry::REPLACEMENT, guardRow()))->toBe('2.000')
        ->and((string) ReceivingRecords::room(ReceiptEntry::ORIGINAL, guardRow(['closed_qty_base' => '1.000', 'dropship_confirmed_net_qty_base' => '1.000'])))->toBe('0.000')
        ->and((string) ReceivingRecords::room(ReceiptEntry::REPLACEMENT, guardRow(['replacement_received_net_qty_base' => '2.000'])))->toBe('0.000');
});

it('generates an opaque lot code, naming nothing of the product, the company or the cost (PJ-23)', function () {
    expect(ReceivingRecords::lotCode())->toMatch('/^LOT-[A-Z0-9]{12}$/')
        ->and(ReceivingRecords::lotCode())->not->toBe(ReceivingRecords::lotCode());
});

it('regenerates a colliding lot code at most five times, then refuses (FU-13)', function () {
    $tried = [];
    $code = ReceivingRecords::withLotCode(function (string $code) use (&$tried): int {
        $tried[] = $code;

        return count($tried) === 3 ? 1 : 0;
    });

    expect($tried)->toHaveCount(3)->and($code)->toBe($tried[2]);

    $attempts = 0;
    expect(function () use (&$attempts) {
        ReceivingRecords::withLotCode(function () use (&$attempts): int {
            $attempts++;

            return 0;
        });
    })->toThrow(Refused::class);
    expect($attempts)->toBe(ReceivingRecords::LOT_CODE_TRIES)->and(ReceivingRecords::LOT_CODE_TRIES)->toBe(5);
});

it('reads one serial however it is typed, so a double scan counts once (BD-18; BR-INV-09)', function () {
    expect(ReceivingRecords::serialKey('SN 0 1'))->toBe('sn01')->and(ReceivingRecords::serialKey('sn01'))->toBe('sn01');
});
