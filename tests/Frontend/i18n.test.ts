// TL-03 (TASK-014): the interface's placeholder replacement held to Laravel's — one pass over the template, the
// longest name first, `:Name` and `:NAME` cased —, a value inserted literally: never read as replacement syntax
// nor for another placeholder, as business data is never changed (LOCALIZATION §3.1, §3.4). The texts are the
// catalogue's own.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import { replace } from '../../resources/js/lib/i18n-core.ts';

const en = JSON.parse(readFileSync(new URL('../../lang/en.json', import.meta.url), 'utf8')) as Record<string, string>;
const id = JSON.parse(readFileSync(new URL('../../lang/id.json', import.meta.url), 'utf8')) as Record<string, string>;
const text = (strings: Record<string, string>, key: string): string => strings[key] ?? assert.fail(`${key} is in the catalogue`);

void test('inserts replacement syntax literally ($&, $1, $$)', () => {
    assert.equal(replace(text(en, 'messages.msg_25'), { kode: '$&' }), 'Code $& is not recognized.');
    assert.equal(replace(text(en, 'messages.msg_25'), { kode: '$1' }), 'Code $1 is not recognized.');
    assert.equal(replace(text(id, 'messages.msg_25'), { kode: "$$ $` $'" }), "Kode $$ $` $' tidak dikenal.");
});

void test('never reads an inserted value for another placeholder', () => {
    assert.equal(
        replace(text(en, 'messages.msg_29'), { waktu: '14.05 WIB', penerbit: 'Admin :waktu' }),
        'A password-setting link for this account was created at 14.05 WIB by Admin :waktu.',
    );
    assert.equal(replace(':a :b', { a: ':b', b: ':a' }), ':b :a');
});

void test('takes the longest of names that overlap, whatever their order', () => {
    assert.equal(replace(':waktu dan :w', { w: 'X', waktu: 'Y' }), 'Y dan X');
    assert.equal(replace(':waktu dan :w', { waktu: 'Y', w: 'X' }), 'Y dan X');
    assert.equal(replace(':w_baru :w', { w: 'X' }), 'X_baru X');
});

void test('keeps Laravel\'s casing and leaves an unnamed placeholder as written', () => {
    assert.equal(replace(':Jenis sudah terbit dengan nomor :nomor.', { jenis: 'kuitansi', nomor: 'ARJ/KWT/2026/10/0001' }), 'Kuitansi sudah terbit dengan nomor ARJ/KWT/2026/10/0001.');
    assert.equal(replace(':KODE / :kode', { kode: 'k7m2' }), 'K7M2 / k7m2');
    assert.equal(replace('Kode :kode tidak dikenal.', {}), 'Kode :kode tidak dikenal.');
    assert.equal(replace('Periksa :count isian.', { count: 3 }), 'Periksa 3 isian.');
});
