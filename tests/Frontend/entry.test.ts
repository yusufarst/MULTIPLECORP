// TL-08 (TASK-015): the presentation logic the design system's components rest on, held to ADMIN_FLOW — IP-13's
// Indonesian number entry, the exact provisional figures of a form, IP-02's one `command_id` per intent and
// §13.2's scanner burst. Pure functions only; the components' behaviour is the browser suite's (TL-09).
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { idle, isFrozen, reduceIntent, type IntentEvent } from '../../resources/js/lib/command-intent.ts';
import { add, multiply, round } from '../../resources/js/lib/decimal.ts';
import { parseDecimal } from '../../resources/js/lib/number-entry.ts';
import { BURST_GAP_MS, BurstDetector } from '../../resources/js/lib/scanner.ts';

void test('IP-13: a dot only between groups of three, the comma as the decimal point', () => {
    assert.deepEqual(parseDecimal('1.250.000', 2), { ok: true, value: '1250000' });
    assert.deepEqual(parseDecimal('1.250.000,5', 2), { ok: true, value: '1250000.5' });
    assert.deepEqual(parseDecimal('3,25', 3), { ok: true, value: '3.25' });
    assert.deepEqual(parseDecimal(' 120 ', 0), { ok: true, value: '120' });
    assert.deepEqual(parseDecimal('007', 0), { ok: true, value: '7' });
    assert.deepEqual(parseDecimal('0,5', 2), { ok: true, value: '0.5' });
    assert.deepEqual(parseDecimal('12,', 2), { ok: true, value: '12' });
});

void test('IP-13: any other dot or character is a validation error, never a reinterpretation', () => {
    for (const input of ['1.5', '1.25', '12.3456', '1.250,000,1', '1,2,3', 'Rp1.000', '1 000', '', ',5', '1.000.00', '.500', '1e3', '١٢']) {
        assert.equal(parseDecimal(input, 2).ok, false, input);
    }
});

void test('IP-13: more decimals than the scale is an error, never rounded', () => {
    assert.deepEqual(parseDecimal('1,255', 2), { ok: false, reason: 'scale' });
    assert.deepEqual(parseDecimal('2,5', 0), { ok: false, reason: 'scale' });
    assert.deepEqual(parseDecimal('-5', 0), { ok: false, reason: 'sign' });
    assert.deepEqual(parseDecimal('−5', 0, true), { ok: true, value: '-5' });
});

void test('provisional figures are exact on the entered strings (UXI-14)', () => {
    assert.equal(multiply('3.5', '12500.75'), '43752.625');
    assert.equal(multiply('0.1', '0.2'), '0.02');
    assert.equal(round('43752.625', 2), '43752.63');
    assert.equal(round('-0.005', 2), '-0.01');
    assert.equal(round('7', 2), '7.00');
    assert.equal(add('0.1', '0.2', '1250000'), '1250000.3');
    assert.equal(add('0', '-0.5', '0.5'), '0.0');
});

void test('IP-02: one command_id per intent, kept for every retry of the same payload', () => {
    let minted = 0;
    const mint = () => `cmd-${String((minted += 1))}`;
    const same = (a: string, b: string) => a === b;
    const trigger: IntentEvent<string> = { type: 'trigger', payload: 'a', mint, same };

    let state = reduceIntent(idle<string>(), trigger);
    assert.equal(state.commandId, 'cmd-1');
    assert.equal(isFrozen(state), true);
    assert.equal(reduceIntent(state, trigger), state, 'a second trigger in flight is dropped');

    state = reduceIntent(state, { type: 'failed' });
    assert.equal(state.phase, 'unknown');
    assert.equal(reduceIntent(state, { ...trigger, payload: 'b' }), state, 'a changed payload cannot start a new intent while unknown');
    state = reduceIntent(state, { type: 'retry' });
    assert.equal(state.commandId, 'cmd-1');
    state = reduceIntent(state, { type: 'not_an_outcome', answer: 'RATE_LIMITED' });
    assert.equal(state.phase, 'unknown', 'a retry answered with no outcome stays open');
    state = reduceIntent(reduceIntent(state, { type: 'retry' }), { type: 'outcome', outcome: 'COMMITTED' });
    assert.equal(state.phase, 'ended');
    assert.equal(reduceIntent(state, trigger).commandId, 'cmd-2', 'the next intent gets its own identifier');
});

void test('IP-02: a first attempt answered but undecided keeps its identifier only for the same payload', () => {
    let minted = 0;
    const mint = () => `cmd-${String((minted += 1))}`;
    const same = (a: string, b: string) => a === b;
    const answered = reduceIntent(reduceIntent(idle<string>(), { type: 'trigger', payload: 'a', mint, same }), { type: 'not_an_outcome', answer: 'VALIDATION' });

    assert.equal(answered.phase, 'answered');
    assert.equal(isFrozen(answered), false);
    assert.equal(reduceIntent(answered, { type: 'trigger', payload: 'a', mint, same }).commandId, 'cmd-1');
    assert.equal(reduceIntent(answered, { type: 'trigger', payload: 'b', mint, same }).commandId, 'cmd-2');
});

void test('§13.2: a fast burst ending in Enter is one scan; typing is not', () => {
    const scanner = new BurstDetector();
    let at = 1000;
    const scan = (text: string, gap: number) => {
        let burst = null;

        for (const key of [...Array.from(text), 'Enter']) {
            burst = scanner.feed(key, (at += gap));
        }

        return burst;
    };

    assert.deepEqual(scan('8991234567890', 8)?.code, '8991234567890');
    assert.equal(scan('ARJ-001', BURST_GAP_MS + 60), null, 'typing at human speed');
    assert.equal(scan('ab', 5), null, 'too short to be a scan');
    assert.equal(scanner.feed('Shift', (at += 5)), null);
});
