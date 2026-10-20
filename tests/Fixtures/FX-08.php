<?php

// FX-08 (TEST_STRATEGY §4): the controlled clock of CONCURRENCY_IDEMPOTENCY HO-19 — instants across WIB
// midnight, a month and a year boundary, a backdated issue, and numbering periods seeded and unseeded
// before, between and inside a seeded coarser one. Tests freeze time on these instants through
// Tests\Support\Clock; business dates are WIB (Asia/Jakarta, UTC+7, no daylight saving).
return [
    'id' => 'FX-08',
    'title' => 'The controlled clock: WIB midnight, a month boundary, backdating and the seeded and unseeded periods of HO-19',
    'source' => 'CONCURRENCY_IDEMPOTENCY HO-19',
    'used_by' => 'TL-05',
    'documents' => ['docs/06-api-performance/CONCURRENCY_IDEMPOTENCY/s19-20-handoff-traceability.md'],
    'timezone' => 'Asia/Jakarta',
    'entries' => [
        'instants' => [
            'before-wib-midnight' => '2026-01-31T23:59:59+07:00',
            'wib-midnight' => '2026-02-01T00:00:00+07:00',
            'utc-previous-day' => '2026-02-01T06:59:59+07:00',
            'before-year-end' => '2026-12-31T23:59:59+07:00',
            'year-boundary' => '2027-01-01T00:00:00+07:00',
        ],
        'backdated' => [
            'recorded_at' => '2026-02-02T09:00:00+07:00',
            'business_date' => '2026-01-30',
        ],
        'periods' => [
            'unseeded-before' => ['period' => '2026-01', 'granularity' => 'month', 'seeded' => false],
            'seeded-first' => ['period' => '2026-02', 'granularity' => 'month', 'seeded' => true],
            'unseeded-between' => ['period' => '2026-03', 'granularity' => 'month', 'seeded' => false],
            'seeded-second' => ['period' => '2026-04', 'granularity' => 'month', 'seeded' => true],
            'seeded-coarser' => ['period' => '2027', 'granularity' => 'year', 'seeded' => true],
            'unseeded-inside-coarser' => ['period' => '2027-03', 'granularity' => 'month', 'seeded' => false],
        ],
    ],
];
