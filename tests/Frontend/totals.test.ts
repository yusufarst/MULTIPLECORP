// TL-03 (TASK-029; DESIGN_SYSTEM §14.3; UXI-14): a composer's provisional totals on the entered strings equal the
// list PostgreSQL's numeric arithmetic — the server's — is held to (tests/Unit/DOC/ProvisionalTotalsTest.php).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import { provisionalTotals } from '../../resources/js/lib/decimal.ts';

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
