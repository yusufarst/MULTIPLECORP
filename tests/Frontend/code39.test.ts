// TL-03 (TASK-027; ROUTE-147; DEP-03): the label's Code 39 symbol — every character nine elements with three wide,
// the code framed by `*`, characters separated by one narrow space — and the characters it refuses.
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { bars, encodable, WIDE } from '../../resources/js/lib/code39.ts';

void test('Code 39: an internal code is framed by the start and stop character, five bars a character', () => {
    const drawn = bars('MC00000001');
    const characters = 'MC00000001'.length + 2;
    const perCharacter = 3 * WIDE + 6;

    assert.equal(drawn.bars.length, characters * 5);
    assert.equal(drawn.width, characters * perCharacter + (characters - 1));
    // `*` is 010010100: bars narrow, narrow, wide, wide, narrow, after a wide first space.
    assert.deepEqual(drawn.bars.slice(0, 5), [{ x: 0, width: 1 }, { x: 4, width: 1 }, { x: 6, width: WIDE }, { x: 10, width: WIDE }, { x: 14, width: 1 }]);
});

void test('Code 39: only digits, capital letters, hyphen, dot and space are drawn', () => {
    assert.equal(encodable('8991234567890'), true);
    assert.equal(encodable('AB-12.3 X'), true);

    for (const code of ['', 'abc', 'A*B', 'A/B', 'Ä']) {
        assert.equal(encodable(code), false, code);
        assert.throws(() => bars(code));
    }
});
