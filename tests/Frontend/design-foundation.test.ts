// TL-03 (TASK-098; CR-002 K-3, K-5, K-7; D-8 #2 and #6): the design foundation held in the sources — a quantity shown whole by one
// helper that every caller uses, the colour pairs of the working direction at the contrast the contract states, the done-stage
// marker never drawn in the pale token, the skeleton's rules and the link drawn as a button.
import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

import { plainQuantity } from '../../resources/js/lib/format.ts';

const root = fileURLToPath(new URL('../../', import.meta.url));
const read = (path: string): string => readFileSync(join(root, path), 'utf8');

function sources(directory: string): string[] {
    return readdirSync(join(root, directory)).flatMap((name) => {
        const path = join(directory, name);

        return statSync(join(root, path)).isDirectory() ? sources(path) : /\.tsx?$/.test(name) ? [path] : [];
    });
}

const files = sources('resources/js').map((path) => ({ path, text: read(path) }));

void test('D-8 #2: a quantity is shown whole — "100" is never "1" — and only the zeros of its scale go', () => {
    const cases: [string, string][] = [
        ['100', '100'],
        ['1000', '1000'],
        ['10', '10'],
        ['100.00', '100'],
        ['10.000', '10'],
        ['120.5000', '120.5'],
        ['0.50', '0.5'],
        ['0', '0'],
        ['0.00', '0'],
        ['-0.50', '-0.5'],
        ['-0.00', '-0'],
        ['', ''],
    ];

    for (const [given, shown] of cases) {
        assert.equal(plainQuantity(given), shown, `"${given}"`);
    }
});

void test('D-8 #2: no caller trims a quantity\'s trailing zeros itself — each uses the one helper', () => {
    const callers = files.filter((file) => /\.replace\(\/\\\.\?0\+\$\//.test(file.text)).map((file) => file.path);

    assert.deepEqual(callers, []);
});

/** WCAG 2.x relative luminance and contrast ratio of two #rrggbb colours. */
function luminance(hex: string): number {
    const channels = [1, 3, 5].map((index) => Number.parseInt(hex.slice(index, index + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));

    return 0.2126 * (channels[0] ?? 0) + 0.7152 * (channels[1] ?? 0) + 0.0722 * (channels[2] ?? 0);
}

function contrast(a: string, b: string): number {
    const [light = 0, dark = 0] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (light + 0.05) / (dark + 0.05);
}

const tokens = read('resources/css/tokens.css');

function token(name: string): string {
    const match = new RegExp(`(?:^|[\\s;{])${name.replace(/-/g, '\\-')}\\s*:\\s*(#[0-9a-fA-F]{6})\\s*;`, 'm').exec(tokens);
    assert.ok(match, `${name} is declared as a hex colour`);

    return match[1] ?? '';
}

void test('K-3: every text pair of the working direction reaches 4.5:1 on the surface it sits on (GR-04)', () => {
    const pairs: [string, string][] = [
        ['--color-text-primary', '--color-canvas'],
        ['--color-text-primary', '--color-surface'],
        ['--color-text-secondary', '--color-surface'],
        ['--color-text-muted', '--color-surface'],
        ['--color-text-muted-canvas', '--color-canvas'],
        ['--color-text-on-primary', '--brand-primary'],
        ['--color-text-on-primary', '--brand-accent'],
        ['--color-text-on-lime', '--brand-tint-100'],
        ['--brand-accent-link', '--color-surface'],
        ['--brand-accent-link', '--color-canvas'],
    ];
    const low = pairs.filter(([ink, ground]) => contrast(token(ink), token(ground)) < 4.5).map(([ink, ground]) => `${ink} on ${ground}: ${contrast(token(ink), token(ground)).toFixed(2)}`);

    assert.deepEqual(low, []);
    assert.equal(token('--color-text-muted-canvas'), '#6f7074', 'the canvas\'s muted text (the Owner\'s correction 3)');
});

void test('K-3: a control edge, the focus ring and the done-stage marker reach 3:1 on each surface (GR-01; 1.4.11)', () => {
    const pairs: [string, string][] = [
        ['--color-border-input', '--color-surface'],
        ['--color-border-input', '--color-canvas'],
        ['--brand-accent', '--color-surface'],
        ['--brand-accent', '--color-canvas'],
        ['--brand-progress', '--color-surface'],
    ];
    const low = pairs.filter(([edge, ground]) => contrast(token(edge), token(ground)) < 3).map(([edge, ground]) => `${edge} on ${ground}: ${contrast(token(edge), token(ground)).toFixed(2)}`);

    assert.deepEqual(low, []);
});

void test('K-3: the pale done-stage token (2.06:1 on white) draws no marker that carries information — cobalt does, beside its check', () => {
    assert.ok(contrast(token('--brand-step-done-dot'), '#ffffff') < 3, 'the pale token is a working value, below 3:1 — so it may not carry information');
    assert.deepEqual(
        files.filter((file) => /step-done-dot/.test(file.text)).map((file) => file.path),
        [],
        'no component draws in it',
    );
});

void test('K-5: the pulse and the fade-in are tokens, the skeleton is hidden from assistive technology and still under reduced motion', () => {
    assert.match(tokens, /--animate-pulse:\s*pulse 1\.4s ease-in-out infinite;/);
    assert.match(tokens, /--animate-fade-in:\s*fade-in var\(--mc-dur-moderate\) var\(--ease-reveal\) both;/);
    assert.match(tokens, /@keyframes pulse\s*\{/);
    assert.match(tokens, /@keyframes fade-in\s*\{/);

    const skeleton = read('resources/js/components/ui/skeleton.tsx');
    assert.match(skeleton, /aria-hidden="true"/);
    assert.match(skeleton, /animate-pulse/);
    assert.match(skeleton, /motion-reduce:animate-none/);
});

void test('K-5: a region loads behind a skeleton after the spinner\'s 300 ms, its status line there from the first render', () => {
    const region = read('resources/js/components/region-state.tsx');
    const loading = region.slice(region.indexOf('export function RegionLoading'), region.indexOf('export function RegionEmpty'));

    assert.match(loading, /useDelayed\(true\)/);
    assert.match(loading, /role="status"/);
    assert.match(loading, /<Skeleton /);
    assert.doesNotMatch(loading, /<Spinner/);
    // The status element is not inside the delayed branch: the live region exists before the words arrive.
    assert.ok(loading.indexOf('role="status"') < loading.indexOf('{shown'), 'the live region precedes the delayed shapes');
});

void test('D-8 #6: a link drawn as a button stays a plain link, a real button keeps its type', () => {
    const button = read('resources/js/components/ui/button.tsx');
    const link = button.slice(button.indexOf('if (isValidElement'), button.indexOf('return (\n        <ButtonPrimitive'));

    assert.match(link, /cloneElement\(/);
    assert.doesNotMatch(link, /ButtonPrimitive/);
    assert.match(button, /<ButtonPrimitive/);
});
