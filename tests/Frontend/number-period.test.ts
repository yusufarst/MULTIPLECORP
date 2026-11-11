// TL-03 (TASK-090; UXS-14; NM-05): the period a backdated business date's number follows — the scheme in force on the
// day, `YYYY` under YEAR and `YYYY-MM` under MONTH —, and none before the type's first scheme.
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { numberPeriod, periodOf } from '../../resources/js/lib/number-period.ts';

const schemes = [
    { from: '2025-01-01', granularity: 'YEAR' },
    { from: '2026-07-01', granularity: 'MONTH' },
];

void test('names the period of the scheme in force on the day', () => {
    assert.equal(numberPeriod(schemes, '2025-12-31'), '2025');
    assert.equal(numberPeriod(schemes, '2026-06-30'), '2026');
    assert.equal(numberPeriod(schemes, '2026-07-01'), '2026-07');
    assert.equal(numberPeriod(schemes, '2026-11-20'), '2026-11');
});

void test('names no period before the first scheme, nor for a type without one', () => {
    assert.equal(numberPeriod(schemes, '2024-12-31'), null);
    assert.equal(numberPeriod([], '2026-11-20'), null);
});

void test('gives a control no periodOf when the form draws no number of the type', () => {
    assert.equal(periodOf(undefined), undefined);
    assert.equal(periodOf(null), undefined);
    assert.equal(periodOf(schemes)?.('2026-10-20'), '2026-10');
});
