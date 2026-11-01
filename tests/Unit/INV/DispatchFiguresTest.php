<?php

use App\Modules\INV\Data\DispatchEntry;
use App\Modules\INV\Data\StockFigures;
use App\Modules\INV\Services\ReceivingRecords;
use App\Support\Command\Refused;
use Brick\Math\BigDecimal;

// TL-03 (TASK-041; L-04; L-31; C-09; BR-RSV-02): a dispatch's reserved and unreserved parts, the quantity's conversion at the line's snapshot, and the open quantity a restoration is capped by.

it('takes the line\'s own reservation first and the rest from AVAILABLE', function (string $base, string $own, string $reserved, string $unreserved) {
    [$first, $second] = DispatchEntry::parts(BigDecimal::of($base), BigDecimal::of($own));

    expect((string) $first)->toBe($reserved)->and((string) $second)->toBe($unreserved);
})->with([
    'all reserved' => ['5', '5', '5', '0'],
    'more reserved than taken' => ['3', '5', '3', '0'],
    'part reserved' => ['5', '2', '2', '3'],
    'none reserved' => ['5', '0', '0', '5'],
]);

it('converts at the snapshot ratio and refuses a fraction the product does not count', function () {
    expect((string) ReceivingRecords::base('2', 12, 0))->toBe('24')
        ->and((string) ReceivingRecords::base('1.5', 2, 0))->toBe('3.0')
        ->and((string) ReceivingRecords::base('1.5', 1, 3))->toBe('1.5')
        ->and(fn () => ReceivingRecords::base('1.5', 1, 0))->toThrow(Refused::class);
});

it('shows the open quantity without the base scale, what left it subtracted', function () {
    expect(StockFigures::remainder('5.000', '2.000', '0.000', '3.000'))->toBe('0')
        ->and(StockFigures::remainder('5.000', '1.000'))->toBe('4')
        ->and(StockFigures::plain('18.000'))->toBe('18');
});
