<?php

use App\Modules\FIN\Data\InvoicePosition;
use App\Modules\FIN\Data\InvoiceValues;
use App\Support\Money;
use Tests\Support\FixtureSets;

// TL-03 (TASK-049; L-16; C-31; CALC-05; CALC-08; BR-FIN-13; BR-FIN-17; FX-01 1, 2, 5, 13): the cap on the net, the output tax apart from the sales value, the proposed tax from configuration, the residual-absorbing split of a value-only termin, the shown state derived.

it('takes the sales value and the billed sum of FX-01\'s AC-10 baseline apart (annex 1; BR-FIN-02)', function () {
    $values = FixtureSets::load('FX-01')['entries'][1]['values'];

    expect(InvoiceValues::gross((string) $values['sales_value'], '0'))->toBe(number_format($values['outstanding_when_billed'], 2, '.', ''));
});

it('keeps the output tax out of the sales value: 10,000,000 + 1,100,000 bills 11,100,000 (annex 13; BR-FIN-17; CALC-08)', function () {
    expect(InvoiceValues::gross('10000000.00', '1100000.00'))->toBe('11100000.00')
        ->and(InvoiceValues::proposedTax('10000000.00', '11.000000', '1', '1', 'HALF_UP'))->toBe('1100000.00');
});

it('proposes a tax from the configured rate, base fraction and rounding, never a hard-coded one (FS-13; DATABASE §12)', function (string $base, string $rate, string $numerator, string $denominator, string $rounding, string $tax) {
    expect(InvoiceValues::proposedTax($base, $rate, $numerator, $denominator, $rounding))->toBe($tax);
})->with([
    'plain' => ['1000000.00', '11.000000', '1', '1', 'HALF_UP', '110000.00'],
    'a base fraction' => ['1000000.00', '12.000000', '11', '12', 'HALF_UP', '110000.00'],
    'half up' => ['100.05', '11.000000', '1', '1', 'HALF_UP', '11.01'],
    'down' => ['100.05', '11.000000', '1', '1', 'DOWN', '11.00'],
]);

it('states what the cap leaves, and holds the cap on the net of a replaced value (L-16; C-31; CM-24)', function () {
    expect(InvoiceValues::remaining('2500000.00', '2000000.00'))->toBe('500000.00')
        ->and(InvoiceValues::remaining('2500000.00', '2600000.00'))->toBe('0.00')
        ->and(InvoiceValues::withinCap('2500000.00', '2000000.00', '500000.00'))->toBeTrue()
        ->and(InvoiceValues::withinCap('2500000.00', '2000000.00', '500001.00'))->toBeFalse()
        ->and(InvoiceValues::withinCap('2500000.00', '2500000.00', '2500000.00', '2500000.00'))->toBeTrue()
        ->and(InvoiceValues::withinCap('2500000.00', '2500000.00', '2600000.00', '2500000.00'))->toBeFalse();
});

it('absorbs the residual in the last value-only termin, the termins\' tax summing to the confirmed tax exactly (BR-FIN-13)', function () {
    $first = InvoiceValues::terminTax('1000000.00', '110000.00', '0.00', '0.00', '333333.00');
    $second = InvoiceValues::terminTax('1000000.00', '110000.00', '333333.00', $first, '333333.00');
    $invoiced = (string) Money::of($first)->plus(Money::of($second));
    $last = InvoiceValues::terminTax('1000000.00', '110000.00', '666666.00', $invoiced, '333334.00');

    expect((string) Money::of($invoiced)->plus(Money::of($last)))->toBe('110000.00')
        ->and($first)->toBe('36667.00')
        ->and($last)->toBe('36666.00');
});

it('derives the shown state from the record and guard, never stored (L-01; BR-FIN-02; annex 2)', function (string $state, bool $billed, ?string $due, string $outstanding, string $written, bool $hold, string $shown) {
    expect(InvoicePosition::of($state, $billed, $due, '2026-10-30', $outstanding, $written, $hold))->toBe($shown);
})->with([
    'a draft' => ['DRAFT', false, null, '0.00', '0.00', false, 'DRAFT'],
    'a void' => ['VOIDED', true, '2026-10-01', '0.00', '0.00', false, 'VOIDED'],
    'issued, not billed' => ['ISSUED', false, null, '1000000.00', '0.00', false, 'ISSUED_NOT_BILLED'],
    'an advance before billing stays unbilled' => ['ISSUED', false, null, '600000.00', '0.00', false, 'ISSUED_NOT_BILLED'],
    'billed' => ['ISSUED', true, '2026-11-30', '1000000.00', '0.00', false, 'BILLED_OUTSTANDING'],
    'past due' => ['ISSUED', true, '2026-10-29', '1000000.00', '0.00', false, 'PAST_DUE_DATE'],
    'on its due date' => ['ISSUED', true, '2026-10-30', '1000000.00', '0.00', false, 'BILLED_OUTSTANDING'],
    'paid' => ['ISSUED', true, '2026-10-01', '0.00', '0.00', false, 'OUTSTANDING_ZERO'],
    'held' => ['ISSUED', true, '2026-10-01', '1000000.00', '0.00', true, 'DISPUTE_HOLD'],
    'written off' => ['ISSUED', true, '2026-10-01', '0.00', '100000.00', false, 'WRITTEN_OFF'],
]);
