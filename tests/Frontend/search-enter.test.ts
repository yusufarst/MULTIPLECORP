// TL-03 (TASK-059; criteria 1, 5; PF-19; IP-15): PG-009's Enter on the search's answer to its own term — one exact hit
// opened, several listed with none chosen for the user (SF-SCAN's ambiguous code), none to the exact scan lookup only for
// an account that may scan, a hit the account cannot open never replaced by another.
import assert from 'node:assert/strict';
import { test } from 'node:test';

import type { SearchResult } from '../../resources/js/lib/navigation.ts';
import { enterDecision, type SearchOutcome } from '../../resources/js/lib/search-client.ts';

const row = (type: string, id: string): SearchResult => ({ type, id, code: 'KODE-1', label: id });
const answer = (exact: SearchResult[]): SearchOutcome => ({ kind: 'answer', answer: { exact, groups: [], notice: null } });
const open = (result: SearchResult) => (result.type === 'pic' ? null : `/${result.type}/${result.id}`);

void test('opens a single exact hit', () => {
    assert.deepEqual(enterDecision(answer([row('product', 'p1')]), true, open), { kind: 'open', href: '/product/p1' });
});

void test('chooses none of several exact hits sharing the code, whatever their types', () => {
    assert.deepEqual(enterDecision(answer([row('product', 'p1'), row('project', 'j1')]), true, open), { kind: 'list' });
    assert.deepEqual(enterDecision(answer([row('serial', 's1'), row('serial', 's2')]), false, open), { kind: 'list' });
    // A hit the account cannot open still counts: the other one is not opened in its place.
    assert.deepEqual(enterDecision(answer([row('pic', 'c1'), row('product', 'p1')]), true, open), { kind: 'list' });
});

void test('opens nothing for a single hit the account cannot open', () => {
    assert.deepEqual(enterDecision(answer([row('pic', 'c1')]), true, open), { kind: 'none' });
});

void test('asks the exact scan lookup only when the search placed no code and the account may scan', () => {
    assert.deepEqual(enterDecision(answer([]), true, open), { kind: 'lookup' });
    assert.deepEqual(enterDecision(answer([]), false, open), { kind: 'none' });
    assert.deepEqual(enterDecision({ kind: 'limited', message: 'MSG-20' }, true, open), { kind: 'lookup' });
    assert.deepEqual(enterDecision({ kind: 'failed' }, false, open), { kind: 'none' });
});
