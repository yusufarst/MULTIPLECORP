// TL-03 (TASK-014): the interface's formats held to LOCALIZATION §3.3's table, the cases the PHP formatter is
// held to as well (tests/Fixtures/Localization/formats.json), and the counted forms of §3.3.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import * as format from '../../resources/js/lib/format.ts';
import { choose, replace } from '../../resources/js/lib/i18n-core.ts';

type Case = [keyof typeof format, (string | boolean)[], string];

const { cases } = JSON.parse(readFileSync(new URL('../Fixtures/Localization/formats.json', import.meta.url), 'utf8')) as { cases: Case[] };

void test('formats each value one way in both languages (§3.3)', () => {
    for (const [name, args, expected] of cases) {
        const formatter = format[name] as (...values: (string | boolean)[]) => string;

        assert.equal(formatter(...args), expected, `${name}(${args.join(', ')})`);
    }
});

void test('refuses a value that is not exact', () => {
    assert.throws(() => format.money('1250000.505'));
    assert.throws(() => format.money('1,5'));
    assert.throws(() => format.date('2026-02-30'));
    assert.throws(() => format.period('2026-13'));
});

void test('chooses a counted form and replaces named placeholders as Laravel does (§3.1, §3.3)', () => {
    const english = 'Check the :count marked field.|Check the :count marked fields.';

    assert.equal(replace(choose(english, 1), { count: 1 }), 'Check the 1 marked field.');
    assert.equal(replace(choose(english, 3), { count: 3 }), 'Check the 3 marked fields.');
    assert.equal(replace(choose('Periksa :count isian yang ditandai.', 3), { count: 3 }), 'Periksa 3 isian yang ditandai.');
    assert.equal(replace(':Jenis sudah terbit dengan nomor :nomor.', { jenis: 'kuitansi', nomor: 'ARJ/KWT/2026/10/0001' }), 'Kuitansi sudah terbit dengan nomor ARJ/KWT/2026/10/0001.');
    assert.equal(replace(':waktu dan :w', { w: 'X', waktu: 'Y' }), 'Y dan X');
});
