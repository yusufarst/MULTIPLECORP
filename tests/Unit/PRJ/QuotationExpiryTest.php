<?php

use App\Modules\PRJ\Services\QuotationRecords;
use Carbon\CarbonImmutable;

// TL-03 (TASK-033; L-25; BR-QUO-01; criterion 2): a sent revision is *Kedaluwarsa* from the WIB day after its validity —
// still valid through 23:59:59 WIB of its last day, expired at 00:00 WIB of the next, whatever the server's UTC date —;
// a draft, an approved or a rejected revision never shows as expired, and EXPIRED is derived, never stored.

afterEach(fn () => CarbonImmutable::setTestNow());

it('keeps a sent revision valid through the last WIB second of its validity and expires it at the next WIB midnight', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-28 16:59:59', 'UTC'));
    expect(QuotationRecords::expired('SENT', '2026-10-28'))->toBeFalse()
        ->and(QuotationRecords::shown('SENT', '2026-10-28'))->toBe('SENT');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-28 17:00:00', 'UTC'));
    expect(QuotationRecords::expired('SENT', '2026-10-28'))->toBeTrue()
        ->and(QuotationRecords::shown('SENT', '2026-10-28'))->toBe('EXPIRED');
});

it('never shows a draft, an answered revision or one without a validity as expired', function (string $state, ?string $validUntil) {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-30 12:00:00', 'UTC'));

    expect(QuotationRecords::shown($state, $validUntil))->toBe($state);
})->with([
    'a draft past its date' => ['DRAFT', '2026-10-28'],
    'an approved one' => ['APPROVED', '2026-10-28'],
    'a rejected one' => ['REJECTED', '2026-10-28'],
    'a sent one without a validity' => ['SENT', null],
]);
