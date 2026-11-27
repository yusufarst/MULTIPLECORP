// TL-03 (TASK-097; DESIGN_SYSTEM §6, §8, D-DS-02; TASK-065 GF-02, GF-07): the shared foundation's corrected rules, held in
// the sources — every responsive utility on one of §6's bands, the spinner's keyframes, the coarse pointer's 16 px fields
// outranking every size utility, and one "WIB" in every template that shows a formatted time.
import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

import { clock, dateClock, dateTime, time } from '../../resources/js/lib/format.ts';
import { replace } from '../../resources/js/lib/i18n-core.ts';

const root = fileURLToPath(new URL('../../', import.meta.url));
const read = (path: string): string => readFileSync(join(root, path), 'utf8');

function sources(directory: string): string[] {
    return readdirSync(join(root, directory)).flatMap((name) => {
        const path = join(directory, name);

        return statSync(join(root, path)).isDirectory() ? sources(path) : /\.tsx?$/.test(name) ? [path] : [];
    });
}

const files = sources('resources/js').map((path) => ({ path, text: read(path) }));

void test('gives every responsive utility one of §6\'s bands — none of Tailwind\'s cleared defaults (criterion 1)', () => {
    const tokens = read('resources/css/tokens.css');
    const bands = [...tokens.matchAll(/--breakpoint-([a-z0-9]+):/g)].map((match) => match[1]);
    assert.deepEqual(bands, ['md', 'lg', 'xl', 'composer']);

    const undefinedBand = /(?:^|[\s"'`])(?:max-)?(?:sm|2xl):[\w[(-]/m;
    const offending = files.filter((file) => undefinedBand.test(file.text)).map((file) => file.path);
    assert.deepEqual(offending, [], 'a utility on a band §6 does not define never applies');
});

void test('defines the spinner\'s turn, one per --mc-dur-spin, which Tailwind\'s cleared animations no longer give (criterion 2)', () => {
    const tokens = read('resources/css/tokens.css');
    assert.match(tokens, /--animate-spin: spin var\(--mc-dur-spin\) linear infinite;/);
    assert.match(tokens, /@keyframes spin \{\s*to \{\s*transform: rotate\(360deg\);/);

    const spinner = read('resources/js/components/ui/spinner.tsx');
    assert.match(spinner, /animate-spin/);
    assert.match(spinner, /motion-reduce:animate-none/);
});

void test('sets a coarse pointer\'s fields to 16 px outside the cascade layers, above every size utility (criterion 3)', () => {
    const app = read('resources/css/app.css');
    const at = app.indexOf('font-size: var(--text-touch)');
    assert.ok(at > 0, 'the coarse pointer rule exists');

    const before = app.slice(0, at);
    const depth = (before.match(/\{/g) ?? []).length - (before.match(/\}/g) ?? []).length;
    assert.equal(depth, 2, 'inside its @media and its selector only, not inside a @layer');
    assert.match(before.slice(before.lastIndexOf('@media')), /^@media \(pointer: coarse\) \{\s*input,\s*select,\s*textarea \{\s*$/);
});

void test('formats a clock without the zone for a template that names "WIB" itself (GF-07)', () => {
    const at = '2026-10-02T07:05:00Z';
    assert.equal(clock(at), '14.05');
    assert.equal(time(at), '14.05 WIB');
    assert.equal(dateClock(at), '02 Okt 2026 14.05');
    assert.equal(dateTime(at), '02 Okt 2026 14.05 WIB');
});

void test('shows one "WIB" in every template that shows a formatted time, in both languages (criterion 7)', () => {
    const catalogues = { id: JSON.parse(read('lang/id.json')) as Record<string, string>, en: JSON.parse(read('lang/en.json')) as Record<string, string> };
    const naming = Object.keys(catalogues.id).filter((key) => /:\w+ WIB/.test(catalogues.id[key] ?? ''));

    // A call that hands such a template a value already ending in "WIB" prints the zone twice.
    const call = /\bt\(\s*'([\w.]+)'\s*,\s*\{([^}]*)\}/g;
    const doubled: string[] = [];
    let used = 0;

    for (const file of files) {
        for (const match of file.text.matchAll(call)) {
            if (!naming.includes(match[1] ?? '')) continue;
            used += 1;

            if (/\b(time|dateTime)\(/.test(match[2] ?? '')) doubled.push(`${file.path}: ${match[1] ?? ''}`);
        }
    }

    assert.ok(used >= 6, 'the templates that name "WIB" are found at their calls');
    assert.deepEqual(doubled, []);

    const at = '2026-10-02T07:05:00Z';
    for (const [lang, strings] of Object.entries(catalogues)) {
        for (const key of naming) {
            const shown = replace(strings[key] ?? '', { v: clock(at), w: clock(at), waktu: dateClock(at), nama: 'Rina' });
            assert.equal(shown.split('WIB').length - 1, 1, `${lang} ${key}: "${shown}"`);
        }
    }
});
