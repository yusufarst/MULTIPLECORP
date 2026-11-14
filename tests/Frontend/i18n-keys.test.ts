// TL-03 (TASK-067; LOCALIZATION §3.6; TEST_STRATEGY §10; DEP-07): the tool that finds a missing key, verified here — every key
// the interface's code names literally, through `t(…)` or `tc(…)`, exists in both string files with the same named
// placeholders —, and no sentence of the interface is built from pieces. Keys built at run time are the rendered pages' to
// prove: the browser layer finds a key shown as itself. A hard-coded string is the lint rule's (`multiplecorp/hardcoded-copy`).
import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, sep } from 'node:path';
import { test } from 'node:test';

import ts from 'typescript';

const root = new URL('../../', import.meta.url);
const strings = (language: 'id' | 'en'): Record<string, string> => JSON.parse(readFileSync(new URL(`lang/${language}.json`, root), 'utf8')) as Record<string, string>;
const id = strings('id');
const en = strings('en');

/** Every `.ts` and `.tsx` file under the interface's code. */
function sources(directory: string): string[] {
    return readdirSync(directory).flatMap((name) => {
        const path = join(directory, name);

        if (statSync(path).isDirectory()) return sources(path);

        return /\.(ts|tsx)$/.test(name) && !/\.d\.ts$/.test(name) ? [path] : [];
    });
}

const interfaceCode = (): string[] => sources(new URL('resources/js', root).pathname.replace(/^\/([A-Za-z]:)/, '$1'));

/** The keys a file names literally: `t('a.b')`, `tc("a.b", …)` — a call on `t`/`tc` with a dotted string literal first. */
export function literalKeys(code: string): string[] {
    const keys: string[] = [];
    const call = /(?<![\w$.])tc?\(\s*(['"])([a-z][a-z0-9_]*(?:\.[a-zA-Z0-9_-]+)+)\1/g;

    for (const match of code.matchAll(call)) {
        if (match[2] !== undefined) keys.push(match[2]);
    }

    return keys;
}

// The set of named placeholders: a counted English string (`one form|other form`, §3.3) names `:count` in each form.
const placeholders = (text: string): string[] => [...new Set([...text.matchAll(/:([a-z_][a-z0-9_]*)/g)].map((match) => match[1] ?? ''))].sort();

/**
 * The sentences a file builds from pieces (LOCALIZATION §3.6), read from its syntax: a `t()`/`tc()` call joined by `+` to
 * words or to another call, a template literal holding such a call beside words of its own, and two such calls side by side
 * as one element's children. A value goes into its key's named placeholder; labels joined by punctuation — "A · B" — are
 * not a sentence.
 */
export function concatenated(code: string): string[] {
    const source = ts.createSourceFile('code.tsx', code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    const found: string[] = [];
    const call = (node: ts.Node | undefined): boolean =>
        node !== undefined && ts.isCallExpression(node) && ts.isIdentifier(node.expression) && ['t', 'tc'].includes(node.expression.text);
    const worded = (node: ts.Node): boolean => (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) && /\p{L}/u.test(node.text);
    const at = (node: ts.Node): string =>
        `${String(source.getLineAndCharacterOfPosition(node.getStart()).line + 1)}: ${node.getText().replace(/\s+/g, ' ').slice(0, 120)}`;
    const plus = (node: ts.Node): node is ts.BinaryExpression => ts.isBinaryExpression(node) && node.operatorToken.kind === ts.SyntaxKind.PlusToken;
    const operands = (node: ts.Expression): ts.Expression[] => (plus(node) ? [...operands(node.left), ...operands(node.right)] : [ts.isParenthesizedExpression(node) ? node.expression : node]);
    const blank = (node: ts.Node): boolean => (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) && node.text.trim() === '';
    // Two calls side by side, nothing but white space between them: one sentence in two keys.
    const adjacent = (pieces: ts.Expression[]): boolean => {
        const kept = pieces.filter((piece) => !blank(piece));

        return kept.some((piece, index) => index > 0 && call(piece) && call(kept[index - 1]));
    };
    const visit = (node: ts.Node): void => {
        // A `+` chain read whole, from its outermost `+`: a call beside words, or two calls with only white space between.
        if (plus(node) && !plus(node.parent)) {
            const pieces = operands(node);

            if ((pieces.some(call) && pieces.some(worded)) || adjacent(pieces)) found.push(at(node));
        }

        if (ts.isTemplateExpression(node) && node.templateSpans.some((span) => call(span.expression))) {
            const words = /\p{L}/u.test([node.head.text, ...node.templateSpans.map((span) => span.literal.text)].join(''));
            const sideBySide = node.templateSpans.some((span, index) => index > 0 && call(span.expression) && call(node.templateSpans[index - 1]?.expression) && (node.templateSpans[index - 1]?.literal.text ?? '').trim() === '');

            if (words || sideBySide) found.push(at(node));
        }

        if (ts.isJsxElement(node) || ts.isJsxFragment(node)) {
            const children = node.children.filter((child) => !(ts.isJsxText(child) && child.text.trim() === ''));

            children.forEach((child, index) => {
                const before = children[index - 1];

                if (before !== undefined && ts.isJsxExpression(before) && ts.isJsxExpression(child) && call(before.expression) && call(child.expression)) {
                    found.push(at(child));
                }
            });
        }

        ts.forEachChild(node, visit);
    };

    visit(source);

    return found;
}

void test('finds the literal keys of a call and nothing else', () => {
    assert.deepEqual(literalKeys(`t('pages.pg_001.title'); tc("messages.msg_20", minutes); x.t('not.this'); format('a.b'); t(key)`), ['pages.pg_001.title', 'messages.msg_20']);
});

void test('finds a sentence built from pieces and nothing else', () => {
    assert.equal(concatenated(`const a = t('x.y') + ' baru';`).length, 1);
    assert.equal(concatenated(`const a = 'Total ' + t('x.y');`).length, 1);
    assert.equal(concatenated(`const a = t('x.y') + t('z.w');`).length, 1);
    assert.equal(concatenated(`const a = t('a.b') + ' ' + t('c.d');`).length, 1);
    assert.equal(concatenated('const a = `${t(\'a.b\')} ${t(\'c.d\')}`;').length, 1);
    assert.equal(concatenated(`const a = t('a.b') + ' · ' + t('c.d');`).length, 0);
    assert.equal(concatenated(`const a = 'Rp ' + format(x);`).length, 0);
    assert.equal(concatenated('const a = `${t(\'x.y\')} baru`;').length, 1);
    assert.equal(concatenated(`const a = <p>{t('a.b')} {t('c.d')}</p>;`).length, 1);
    assert.equal(concatenated(`const a = <p>{t('a.b')} · {t('c.d')}</p>;`).length, 0);
    assert.equal(concatenated('const a = `${t(\'a.b\')} · ${name}`;').length, 0);
    assert.equal(concatenated(`const a = t('a.b', { nomor: String(x + 1) });`).length, 0);
    assert.equal(concatenated(`const a = <p>{t('a.b')} <strong>{name}</strong></p>;`).length, 0);
});

void test('every key the interface names literally exists in both string files with the same placeholders (LOCALIZATION §3.6)', () => {
    const used = new Map<string, string>();

    for (const file of interfaceCode()) {
        for (const key of literalKeys(readFileSync(file, 'utf8'))) {
            if (!used.has(key)) used.set(key, file);
        }
    }

    assert.ok(used.size > 1000, `the scan read the interface's keys (${String(used.size)})`);
    const missing = [...used].filter(([key]) => !(key in id) || !(key in en)).map(([key, file]) => `${key} (${file})`);
    assert.deepEqual(missing, [], 'missing translations: 0');
    const empty = [...used.keys()].filter((key) => (id[key] ?? '').trim() === '' || (en[key] ?? '').trim() === '');
    assert.deepEqual(empty, [], 'no empty string');
    const differing = [...used.keys()].filter((key) => JSON.stringify(placeholders(id[key] ?? '')) !== JSON.stringify(placeholders(en[key] ?? '')));
    assert.deepEqual(differing, [], 'the same named placeholders in both languages');
});

void test('no page builds a sentence from pieces (LOCALIZATION §3.6) — a local fixture page is no page of the application', () => {
    const files = interfaceCode().filter((file) => !file.includes(`${sep}Pages${sep}Fixtures${sep}`));
    const found = files.flatMap((file) => concatenated(readFileSync(file, 'utf8')).map((hit) => `${file}:${hit}`));

    assert.ok(files.length > 100, `the scan read the interface's code (${String(files.length)} files)`);
    assert.deepEqual(found, [], 'no concatenated sentence');
});
