<?php

use App\Modules\FIN\Data\InvoicePosition;
use App\Support\BusinessDate;
use App\Support\Finance\AgingCalculator;
use Carbon\CarbonImmutable;
use Tests\Support\FixtureSets;

// TL-03 (TASK-050; BR-FIN-15; CALC-07; BR-DT-06; BR-FIN-14; FX-01 1–2; FX-08): the buckets at each edge, the WIB day boundary through the controlled clock, an opening receivable without a due date, and the remainder billing activates after an advance.

afterEach(fn () => CarbonImmutable::setTestNow());

it('places a receivable in its bucket at each edge (BR-FIN-15)', function (string $due, int $days, string $bucket) {
    expect(AgingCalculator::daysPast($due, '2026-10-31'))->toBe($days)
        ->and(AgingCalculator::bucket($due, '2026-10-31'))->toBe($bucket);
})->with([
    'not yet due' => ['2026-11-30', 0, AgingCalculator::CURRENT],
    'due today' => ['2026-10-31', 0, AgingCalculator::CURRENT],
    'one day past' => ['2026-10-30', 1, AgingCalculator::DAYS_1_30],
    'thirty days past' => ['2026-10-01', 30, AgingCalculator::DAYS_1_30],
    'thirty-one days past' => ['2026-09-30', 31, AgingCalculator::DAYS_31_60],
    'sixty days past' => ['2026-09-01', 60, AgingCalculator::DAYS_31_60],
    'sixty-one days past' => ['2026-08-31', 61, AgingCalculator::DAYS_61_90],
    'ninety days past' => ['2026-08-02', 90, AgingCalculator::DAYS_61_90],
    'ninety-one days past' => ['2026-08-01', 91, AgingCalculator::OVER_90],
    'across a year' => ['2025-10-31', 365, AgingCalculator::OVER_90],
]);

it('puts an opening receivable without a due date in the migrasi bucket (BR-FIN-14)', function () {
    expect(AgingCalculator::bucket(null, '2026-10-31'))->toBe(AgingCalculator::MIGRASI);
});

it('turns a day past due at midnight in WIB, not in UTC (BR-DT-06; FX-08)', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-30 16:59:59', 'UTC'));
    expect(AgingCalculator::daysPast('2026-10-30', BusinessDate::today()))->toBe(0);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-30 17:00:00', 'UTC'));
    expect(AgingCalculator::daysPast('2026-10-30', BusinessDate::today()))->toBe(1)
        ->and(AgingCalculator::bucket('2026-10-30', BusinessDate::today()))->toBe(AgingCalculator::DAYS_1_30);
});

it('refuses what is no business date, in PHP and in SQL alike', function () {
    expect(fn () => AgingCalculator::daysPast('31-10-2026', '2026-10-31'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => AgingCalculator::bucketSql('b.current_due_date', "2026-10-31'; drop"))->toThrow(InvalidArgumentException::class)
        ->and(fn () => AgingCalculator::bucketSql('b.due; drop', '2026-10-31'))->toThrow(InvalidArgumentException::class);
});

it('shows a billed receivable past its due date, and a disputed one for what it is, keeping its age (BR-FIN-08; CALC-07)', function () {
    expect(InvoicePosition::of('ISSUED', true, '2026-10-30', '2026-10-31', '1000.00', '0.00', false))->toBe(InvoicePosition::PAST_DUE)
        ->and(InvoicePosition::of('ISSUED', true, '2026-10-30', '2026-10-31', '1000.00', '0.00', true))->toBe(InvoicePosition::HOLD)
        ->and(AgingCalculator::bucket('2026-10-30', '2026-10-31'))->toBe(AgingCalculator::DAYS_1_30);
});

it('holds FX-01\'s AC-10 baseline and advance: billing activates only the unpaid remainder (annex 1, 2; BR-FIN-04)', function () {
    $baseline = FixtureSets::load('FX-01')['entries'][1]['values'];
    $advance = FixtureSets::load('FX-01')['entries'][2]['values'];

    expect($baseline['outstanding_when_billed'])->toBe($baseline['invoice'])
        ->and($advance['advance_payment'])->toBe(400_000)
        ->and($baseline['invoice'] - $advance['advance_payment'])->toBe(600_000);
});
