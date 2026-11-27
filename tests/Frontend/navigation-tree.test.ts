// TL-03 (TASK-099; CR-002 K-4; INFORMATION_ARCHITECTURE §5.3; NAVIGATION_CONTRACTS NV-01–NV-08): the *Menu*'s tree held in the
// source — eight modules in three groups, their 37 pages as leaves, nothing lost; each leaf present only for an account whose
// capabilities open it (AZ-01), the server still deciding every request; the group never a step of a path.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

import { type AccountHints, currentLeaf, GROUPS, navigationTree } from '../../resources/js/lib/navigation.ts';

const source = readFileSync(fileURLToPath(new URL('../../resources/js/lib/navigation.ts', import.meta.url)), 'utf8');

/** Every capability the navigation names, and a company in scope: the account that reaches every page. */
const capabilities = [...new Set([...source.matchAll(/'([a-z_]+\.[a-z_]+)'/g)].map((match) => match[1] ?? ''))];
const everything: AccountHints = { name: 'Semua', capabilities, companies: [{ id: 'c1', code: 'ARJ', name: 'Satu' }] };
const nothing: AccountHints = { name: 'Tanpa', capabilities: [], companies: [] };

const leaves = (account: AccountHints) => navigationTree(account).flatMap((group) => group.modules.flatMap((entry) => entry.leaves));

void test('K-4: eight modules in three groups, their 37 pages as leaves — nothing lost for an account that reaches them all', () => {
    const tree = navigationTree(everything);

    assert.deepEqual(tree.map((group) => group.modules.length), [2, 3, 3]);
    assert.equal(tree.flatMap((group) => group.modules).length, 8);
    assert.deepEqual(
        tree.flatMap((group) => group.modules).map((entry) => [entry.module.key, entry.leaves.length]),
        [['pembelian', 1], ['gudang', 10], ['keuangan', 7], ['dokumen', 5], ['laporan', 3], ['data-master', 5], ['aktivitas', 2], ['pengaturan', 4]],
    );
    assert.equal(leaves(everything).length, 37);
    assert.equal(new Set(leaves(everything).map((leaf) => leaf.pg)).size, 37, 'each page once');
    assert.equal(new Set(leaves(everything).map((leaf) => leaf.href)).size, 37, 'each address once');
});

void test('K-4: the groups are the three labels, proposed copy kept in the catalogue (DIR-134 E7)', () => {
    assert.deepEqual(GROUPS.map((group) => group.label), ['shell.nav.group_operations', 'shell.nav.group_finance_documents', 'shell.nav.group_administration']);
    assert.deepEqual(GROUPS.flatMap((group) => group.modules).sort(), ['aktivitas', 'data-master', 'dokumen', 'gudang', 'keuangan', 'laporan', 'pembelian', 'pengaturan']);
});

void test('AZ-01: a leaf an account holds no capability for is not in its tree — absent, never disabled', () => {
    const open = leaves(nothing).map((leaf) => leaf.pg);

    for (const leaf of leaves(everything)) {
        if (leaf.allowed !== undefined) {
            assert.equal(leaf.allowed(nothing), false, `${leaf.pg} for an account with nothing`);
            assert.ok(!open.includes(leaf.pg), `${leaf.pg} is absent`);
        }
    }

    // Without a company in scope no company-owned module opens (CS-05): only what needs neither capability nor company.
    assert.ok(!navigationTree({ ...everything, companies: [] }).some((group) => group.modules.some((entry) => entry.module.key === 'pembelian')));
});

void test('K-4: a page is marked by the longest leaf path its address starts with, and a group is never a step of a path', () => {
    const all = leaves(everything);

    assert.equal(currentLeaf(all, '/gudang/stok'), 'PG-018');
    assert.equal(currentLeaf(all, '/gudang/stok/produk-1'), 'PG-018');
    assert.equal(currentLeaf(all, '/gudang/riwayat-stok'), 'PG-020');
    assert.equal(currentLeaf(all, '/tidak-ada'), null);
    assert.ok(all.every((leaf) => leaf.href.startsWith('/') && !GROUPS.some((group) => group.key === leaf.href.split('/')[1])), 'no path begins with a group');
});
