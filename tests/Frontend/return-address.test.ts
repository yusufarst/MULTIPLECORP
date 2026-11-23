// TL-03 (TASK-096 A; IP-16; NV-04; ER-06): the return address of evidence registered from a form keeps the page's own keys only,
// each once — PG-017's address one size whatever its lines and whatever else its address holds, never past the return list's
// bound (2,048) or its key rule, where the registration would answer on PG-038 and the purchase's input be lost.
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { returnAddress } from '../../resources/js/lib/return-address.ts';

const id = (n: number): string => `00000000-0000-4000-8000-${String(n).padStart(12, '0')}`;

void test('PG-017 returns with its company and its own keys, never its lines\' products, its supplier or a foreign key, whatever the lines', () => {
    const lines = Array.from({ length: 500 }, (_, n) => id(n)).join(',');
    const address = returnAddress('/pembelian/baru', `?produk=${id(1)}&pemasok=${id(2)}&harga=${lines}&Kunci-Asing=x`, ['proyek', 'produk', 'dari'], { perusahaan: id(3) });

    assert.equal(address, `/pembelian/baru?produk=${id(1)}&perusahaan=${id(3)}`);
    assert.ok(address.length < 2048);
});

void test('a key the page needs back replaces the one in its address, and a repeated key is kept once, its last value', () => {
    assert.equal(returnAddress('/pembelian/baru', `?dari=${id(2)}&dari=${id(4)}&perusahaan=${id(1)}`, ['dari', 'perusahaan'], { perusahaan: id(3) }), `/pembelian/baru?dari=${id(4)}&perusahaan=${id(3)}`);
});

void test('an address with no query left is the path alone', () => {
    assert.equal(returnAddress('/pembelian/baru', '?harga=a%2Cb', ['proyek'], {}), '/pembelian/baru');
    assert.equal(returnAddress('/pembelian/baru', '', [], {}), '/pembelian/baru');
});
