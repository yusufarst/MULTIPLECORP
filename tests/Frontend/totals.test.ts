// TL-03 (TASK-029; DESIGN_SYSTEM §14.3; UXI-14): a composer's provisional totals on the entered strings equal the
// list PostgreSQL's numeric arithmetic — the server's — is held to (tests/Unit/DOC/ProvisionalTotalsTest.php).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import { multiply, provisionalTotals, quotient } from '../../resources/js/lib/decimal.ts';

type Case = { lines: [string, string][]; tax_percent: string; subtotal: string; tax: string; total: string };

const { cases } = JSON.parse(readFileSync(new URL('./provisional-totals.json', import.meta.url), 'utf8')) as { cases: Case[] };

void test('provisional totals: each case of the shared list, line by line, to the sen', () => {
    for (const expected of cases) {
        const totals = provisionalTotals(expected.lines, expected.tax_percent);

        assert.equal(totals.subtotal, expected.subtotal);
        assert.equal(totals.tax, expected.tax);
        assert.equal(totals.total, expected.total);
    }
});

void test('provisional totals: no line is a zero subtotal and no tax', () => {
    assert.deepEqual(provisionalTotals([], '11'), { lines: [], subtotal: '0.00', tax: '0.00', total: '0.00' });
});

// The invoice's proposed tax: the same cases as tests/Unit/FIN/InvoiceValuesTest (TASK-049; FS-13), exact where a float is not.
for (const [base, rate, numerator, denominator, mode, tax] of [
    ['1000000.00', '11.000000', '1', '1', 'HALF_UP', '110000.00'],
    ['1000000.00', '12.000000', '11', '12', 'HALF_UP', '110000.00'],
    ['100.05', '11.000000', '1', '1', 'HALF_UP', '11.01'],
    ['100.05', '11.000000', '1', '1', 'DOWN', '11.00'],
    ['1.15', '50.000000', '1', '1', 'HALF_UP', '0.58'],
    ['99999999999.99', '11.000000', '1', '1', 'DOWN', '10999999999.99'],
] as const) {
    void test(`proposes ${tax} on ${base} at ${rate}% × ${numerator}/${denominator} by ${mode}`, () => {
        assert.equal(quotient(multiply(multiply(base, rate), numerator), multiply(denominator, '100'), 2, mode), tax);
    });
}

