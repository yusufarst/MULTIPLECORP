// TL-08 (TASK-015; acceptance criterion 1): every token DESIGN_SYSTEM §5 adopts or adapts exists in
// resources/css/tokens.css with the value §5 gives it, and no token §5 excludes is defined. A token lives under
// its own name or under the Tailwind key its row's last column gives it — `--tint-olive` as `--color-tint-olive`,
// `--text-title-size` · `-lh` · `-track` as `--text-title` with `--line-height` and `--letter-spacing`. The
// specification is read as written, so a change on either side fails here until both agree.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const spec = readFileSync(new URL('../../docs/07-ux-design/DESIGN_SYSTEM.md', import.meta.url), 'utf8');
const css = readFileSync(new URL('../../resources/css/tokens.css', import.meta.url), 'utf8');

const section = spec.slice(spec.indexOf('\n## 5. Tokens'), spec.indexOf('\n## 6.'));

/** `--color-{a,b}-{x,y}` → its four names. */
function expand(name: string): string[] {
    const match = /\{([^}]+)\}/.exec(name);

    return match === null ? [name] : (match[1] ?? '').split(',').flatMap((part) => expand(name.replace(match[0], part.trim())));
}

type Row = { disposition: 'ADOPT' | 'ADAPT' | 'EXCLUDE'; tokens: { name: string; value: string | null }[]; key: string };

function rows(): Row[] {
    const found: Row[] = [];

    for (const line of section.split('\n')) {
        const cells = line.split('|').map((cell) => cell.trim());
        const disposition = /^(ADOPT|ADAPT|EXCLUDE)\b/.exec(cells.find((cell) => /^(ADOPT|ADAPT|EXCLUDE)\b/.test(cell)) ?? '')?.[1] as Row['disposition'] | undefined;

        if (!line.startsWith('|') || disposition === undefined) {
            continue;
        }

        const first = cells[1] ?? '';
        const tokens = [...first.matchAll(/`(--[a-z0-9{},*-]+)`(?:\s+`([^`]+)`)?/g)].flatMap(([, name = '', value]) =>
            name.includes('*') ? [] : expand(name).map((each) => ({ name: each, value: value ?? null })),
        );

        if (tokens.length > 0) {
            found.push({ disposition, tokens, key: cells.at(-2) ?? '' });
        }
    }

    return found;
}

/** The names a row's token may be declared under: its own, or its Tailwind key. */
function candidates(name: string, key: string): string[] {
    const keys = [...key.matchAll(/`(--[a-z0-9-]+)`/g)].map(([, each = '']) => each);
    const typography = /^--(text-[a-z0-9-]+?)-(size|lh|track)$/.exec(name);
    const names = [name, ...keys.filter((each) => each.endsWith(name.slice(2)))];

    if (key.includes('--color-')) {
        names.push(`--color-${name.slice(2)}`);
    }

    if (typography !== null) {
        const [, base = '', part] = typography;
        names.push(part === 'size' ? `--${base}` : `--${base}--${part === 'lh' ? 'line-height' : 'letter-spacing'}`);
    }

    return names;
}

function declaredFor(name: string, key: string): string | null {
    for (const each of candidates(name, key)) {
        const value = declared(each);

        if (value !== null) {
            return value;
        }
    }

    return null;
}

function declared(name: string): string | null {
    const match = new RegExp(`(?:^|[\\s;{])${name.replace(/[-]/g, '\\-')}\\s*:\\s*([^;]+);`, 'm').exec(css);

    return match === null ? null : (match[1] ?? '').trim();
}

void test('§5 is read: its tables give tokens with dispositions', () => {
    const all = rows();
    assert.ok(all.filter((row) => row.disposition !== 'EXCLUDE').length >= 20, 'adopted and adapted rows');
    assert.ok(all.some((row) => row.disposition === 'EXCLUDE'), 'excluded rows');
});

void test('every adopted or adapted token exists with §5 value (ADOPT, ADAPT)', () => {
    const missing: string[] = [];
    const differing: string[] = [];

    for (const row of rows().filter((each) => each.disposition !== 'EXCLUDE')) {
        for (const token of row.tokens) {
            const value = declaredFor(token.name, row.key);

            if (value === null) {
                missing.push(token.name);
            } else if (token.value !== null && /^#[0-9a-f]{3,8}$/i.test(token.value) && /^#[0-9a-f]{3,8}$/i.test(value) && value.toLowerCase() !== token.value.toLowerCase()) {
                differing.push(`${token.name}: ${value} ≠ ${token.value}`);
            }
        }
    }

    assert.deepEqual(missing, [], 'tokens §5 keeps are declared');
    assert.deepEqual(differing, [], 'colour values are §5 values');
});

void test('no excluded token is defined (EXCLUDE)', () => {
    const defined = rows()
        .filter((row) => row.disposition === 'EXCLUDE')
        .flatMap((row) => row.tokens)
        .filter((token) => declared(token.name) !== null)
        .map((token) => token.name);

    assert.deepEqual(defined, []);
});
