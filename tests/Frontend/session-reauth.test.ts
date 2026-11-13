// TL-03 (TASK-093; UXH-02; IP-11): the in-place re-authentication resumes only the very account it covered, and reads a
// success only from the endpoint's own JSON answer — never from a redirect followed to a page, as a signed-in
// session's sign-in request is answered by the guest guard without its password being checked.
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { dialogAnswer, sameAccount } from '../../resources/js/lib/session-core.ts';

const hints = { name: 'Dimas Uji', capabilities: ['projects.view'], companies: [{ id: 'a', code: 'ARJ', name: 'PT A' }] };

void test('two accounts whose hints match are still two accounts: the keys decide, the hints must match too', () => {
    assert.equal(sameAccount(hints, structuredClone(hints), 'key-a', 'key-a'), true);
    assert.equal(sameAccount(hints, structuredClone(hints), 'key-a', 'key-b'), false);
    assert.equal(sameAccount(hints, { ...hints, name: 'Bayu Uji' }, 'key-a', 'key-a'), false);
    assert.equal(sameAccount(hints, structuredClone(hints), null, 'key-a'), false);
    assert.equal(sameAccount(hints, structuredClone(hints), 'key-a', null), false);
});

void test('a redirect followed to a page is never a success, whatever its status', () => {
    assert.equal(dialogAnswer(200, true, 'text/html; charset=UTF-8', {}), 'failed');
    assert.equal(dialogAnswer(200, true, 'application/json', { signed_in: true }), 'failed');
    assert.equal(dialogAnswer(200, false, 'text/html; charset=UTF-8', {}), 'failed');
    assert.equal(dialogAnswer(204, false, null, {}), 'failed');
});

void test('only the endpoint\'s own JSON answer is a success, a refusal stays a refusal', () => {
    assert.equal(dialogAnswer(200, false, 'application/json', { signed_in: true }), 'ok');
    assert.equal(dialogAnswer(200, false, 'application/json', { confirmed: true }), 'ok');
    assert.equal(dialogAnswer(200, false, 'application/json', { two_factor: true }), 'two_factor');
    assert.equal(dialogAnswer(200, false, 'application/json', { signed_in: true, enrolment_only: true, location: '/akun/dua-langkah' }), 'enrolment');
    assert.equal(dialogAnswer(200, false, 'application/json', {}), 'failed');
    assert.equal(dialogAnswer(422, false, 'application/json', { message: 'MSG-19' }), 'failed');
    assert.equal(dialogAnswer(419, false, 'application/json', { expired: true }), 'expired');
});
