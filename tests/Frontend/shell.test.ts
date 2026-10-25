// TL-08 (TASK-016; acceptance criteria 5, 7, 8): the shell's rules without a browser — the entries each persona's
// capabilities allow, absent rather than disabled and hidden while their page is not built (NV-08; RC-06); which
// answer opens which dialog (UXH-11); what is a background visit (RB-05); and the interrupted visit sent again
// unchanged, so its `command_id` replays (HO-02).
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { bottomEntries, CREATE, currentKey, MODULES, present, primaryEntries, targetOf, workEntries, type AccountHints } from '../../resources/js/lib/navigation.ts';
import { outcomeFor, type SearchOutcome } from '../../resources/js/lib/search-client.ts';
import { BACKGROUND_HEADER, deadlineNoticeDue, interruption, isBackground, minutesUntil, replayOf, sameAccount, sessionOver } from '../../resources/js/lib/session-core.ts';

const ALL = new Set(['PG-006', 'PG-007', 'PG-008', 'PG-010', 'PG-011', 'PG-013', 'PG-015', 'PG-017', 'PG-018', 'PG-019', 'PG-021', 'PG-035', 'PG-038', 'PG-041', 'PG-043', 'PG-046', 'PG-051', 'PG-054', 'PG-055', 'PG-056', 'PG-059', 'PG-063', 'PG-065']);
const ARJ = { id: '01a1', code: 'ARJ', name: 'PT Arjuna Sarana' };
const account = (...capabilities: string[]): AccountHints => ({ name: 'Uji', capabilities, companies: [ARJ] });
const keys = (entries: { key: string }[]) => entries.map((entry) => entry.key);

const owner = account('review.view', 'scope.all_companies', 'projects.view', 'projects.manage', 'purchase.manage', 'stock.view', 'stock.receive', 'finance.view', 'payment.record', 'cost.view', 'evidence.upload', 'audit.view', 'users.manage');
const dimas = account('projects.view', 'projects.manage', 'purchase.manage', 'finance.view', 'payment.record', 'cost.view', 'evidence.upload');
const bayu = account('stock.view', 'stock.receive', 'stock.dispatch', 'evidence.upload');

void test('§5.2: the Owner\'s and an Admin\'s primary entries and phone bar (INT-003)', () => {
    assert.deepEqual(keys(primaryEntries(owner, ALL)), ['beranda', 'proyek', 'pekerjaan', 'tinjauan']);
    assert.deepEqual(keys(bottomEntries(owner, ALL)), ['beranda', 'proyek']);
    assert.deepEqual(keys(workEntries(owner, ALL)), ['pekerjaan', 'tinjauan']);
    assert.deepEqual(keys(primaryEntries(dimas, ALL)), ['pekerjaan', 'proyek']);
    assert.deepEqual(keys(primaryEntries(bayu, ALL)), ['pekerjaan']);
    assert.deepEqual(keys(workEntries(dimas, ALL)), []);
});

void test('§5.3 and §10: *Menu lengkap* and "Buat" by capability, Owner-only modules absent for an Admin (INT-003, INT-004; criterion 5)', () => {
    assert.deepEqual(keys(present(MODULES, owner, ALL)), ['pembelian', 'gudang', 'keuangan', 'dokumen', 'laporan', 'data-master', 'aktivitas', 'pengaturan']);
    assert.deepEqual(keys(present(MODULES, dimas, ALL)), ['pembelian', 'keuangan', 'dokumen', 'laporan', 'data-master']);
    assert.deepEqual(keys(present(MODULES, bayu, ALL)), ['gudang', 'laporan', 'data-master']);
    assert.deepEqual(keys(present(CREATE, owner, ALL)), ['proyek', 'pembelian', 'barang-masuk', 'pembayaran', 'unggah']);
    assert.deepEqual(keys(present(CREATE, dimas, ALL)), ['proyek', 'pembelian', 'pembayaran', 'unggah']);
    assert.deepEqual(keys(present(CREATE, bayu, ALL)), ['barang-masuk', 'unggah']);
});

void test('CS-05: without a company grant only S0 and, with `stock.view`, the pool stay — no company-owned module or "Buat" item', () => {
    const ungranted = (...capabilities: string[]): AccountHints => ({ ...account(...capabilities), companies: [] });

    assert.deepEqual(keys(primaryEntries(ungranted('projects.view', 'finance.view'), ALL)), ['pekerjaan']);
    assert.deepEqual(keys(present(MODULES, ungranted('projects.view', 'finance.view', 'cost.view'), ALL)), ['data-master']);
    assert.deepEqual(keys(present(MODULES, ungranted('stock.view'), ALL)), ['gudang', 'laporan', 'data-master']);
    assert.deepEqual(keys(present(MODULES, ungranted('stock.receive'), ALL)), ['data-master']);
    assert.deepEqual(keys(present(CREATE, ungranted('stock.receive', 'evidence.upload'), ALL)), []);
});

void test('RC-06: an entry whose page is not built is absent', () => {
    assert.deepEqual(keys(primaryEntries(owner, new Set())), []);
    assert.deepEqual(keys(present(MODULES, owner, new Set(['PG-015']))), ['pembelian']);
});

void test('§6.1: the current entry by the address\'s first segment', () => {
    assert.equal(currentKey(present(MODULES, owner, ALL), '/gudang/barang-masuk/01a1'), 'gudang');
    assert.equal(currentKey(primaryEntries(owner, ALL), '/proyek/01a1/penyelesaian'), 'proyek');
    assert.equal(currentKey(primaryEntries(owner, ALL), '/data-master/klien'), null);
});

void test('IP-17: an answer belongs to its term — a changed term has none until its own arrives', () => {
    const outcome: SearchOutcome = { kind: 'answer', answer: { exact: [], groups: [], notice: null } };
    const settled = { term: 'ARJ/PRJ/2026/0007', outcome };

    assert.equal(outcomeFor(settled, 'ARJ/PRJ/2026/0007'), outcome);
    assert.equal(outcomeFor(settled, '  ARJ/PRJ/2026/0007 '), outcome);
    assert.equal(outcomeFor(settled, 'ARJ/PRJ/2026/00078'), null);
    assert.equal(outcomeFor(settled, ''), null);
    assert.equal(outcomeFor(null, 'ARJ/PRJ/2026/0007'), null);
});

void test('§10: a result opens its page, only once built, products by `stock.view`', () => {
    const product = { type: 'product', id: 'p1', code: 'KRT', label: 'Kertas' };

    assert.equal(targetOf(product, bayu, ALL), '/gudang/stok/p1');
    assert.equal(targetOf(product, dimas, ALL), '/data-master/produk?produk=p1');
    assert.equal(targetOf({ type: 'invoice', id: 'i1', code: 'X', label: 'X' }, dimas, ALL), '/keuangan/invoice/i1');
    assert.equal(targetOf({ type: 'document', id: 'd1', code: 'X', label: 'X' }, dimas, new Set(['PG-036'])), '/dokumen/d1');
    assert.equal(targetOf(product, bayu, new Set()), null);
    assert.equal(targetOf({ type: 'users', id: 'u1', code: null, label: 'X' }, owner, ALL), null);
});

void test('UXH-11: 401 and 419 sign in again in place, 423 asks for the step-up, others pass (criteria 7, 8)', () => {
    assert.equal(interruption(401), 'reauth');
    assert.equal(interruption(419), 'reauth');
    assert.equal(interruption(423), 'step_up');
    assert.equal(interruption(409), null);
    assert.equal(interruption(403), null);
});

void test('RB-05: only a GET poll, deferred prop or prefetch is background', () => {
    const visit = { url: '/x', method: 'get', data: {}, headers: {} };

    assert.equal(isBackground({ ...visit, async: true }), true);
    assert.equal(isBackground({ ...visit, prefetch: true }), true);
    assert.equal(isBackground(visit), false);
    assert.equal(isBackground({ ...visit, method: 'post', async: true }), false);
});

void test('HO-02: the interrupted visit is sent again with the same data, so the same command_id', () => {
    const onSuccess = () => 'reported';
    const replay = replayOf({ url: new URL('https://x/keuangan/pembayaran'), method: 'post', data: { command_id: 'cmd-1', amount: '300000' }, headers: { 'X-Custom': '1', [BACKGROUND_HEADER]: '1' }, onSuccess } as never);

    assert.equal(replay.url, 'https://x/keuangan/pembayaran');
    assert.deepEqual(replay.options.data, { command_id: 'cmd-1', amount: '300000' });
    assert.deepEqual(replay.options.headers, { 'X-Custom': '1' });
    assert.equal(replay.options.preserveState, true);
    assert.equal(replay.callbacks.onSuccess, onSuccess);
});

void test('IP-11: MSG-34 from fifteen minutes before the absolute limit; the minutes left; the same account', () => {
    const deadline = 1_000_000_000;

    assert.equal(deadlineNoticeDue(deadline, deadline - 16 * 60_000), false);
    assert.equal(deadlineNoticeDue(deadline, deadline - 15 * 60_000), true);
    assert.equal(deadlineNoticeDue(deadline, deadline + 1), false);
    assert.equal(deadlineNoticeDue(null, deadline), false);
    // Past the absolute limit a 401 never opens the dialog: the full document load of sign-in.
    assert.equal(sessionOver(deadline, deadline - 1), false);
    assert.equal(sessionOver(deadline, deadline), true);
    assert.equal(sessionOver(null, deadline), false);
    assert.equal(minutesUntil(deadline, deadline - 61_000), 2);
    assert.equal(minutesUntil(deadline, deadline + 5), 0);
    assert.equal(sameAccount({ name: 'A', capabilities: ['x'] }, { name: 'A', capabilities: ['x'] }), true);
    assert.equal(sameAccount({ name: 'A', capabilities: ['x'] }, { name: 'B', capabilities: ['x'] }), false);
});
