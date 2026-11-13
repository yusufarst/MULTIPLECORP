import { readFileSync } from 'node:fs';

import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

// TL-10 (TASK-067; LOCALIZATION §3.4, §3.6, §3.7; TEST_STRATEGY §10): the page as rendered — the shell, `#konten` and an
// open dialog — in the language asked for: no key shown as itself, no unfilled placeholder, no control without a name,
// on an English page no Indonesian interface string other than §3.4's values, names and document type names, no
// horizontal scroll outside a data table, no clipped label, its targets at their sizes; and its structure, which a spec
// compares between the two languages of the same page at the same width (§3.7 "the same regions and actions in the same
// order").

export type Language = 'id' | 'en';

/** TL-11's phone width (SV-02). */
export const PHONE = { width: 390, height: 844 } as const;

/** Roles and counts only, never text: the page's landmarks, then the heading levels and the buttons and links of
    `#konten` and of an open dialog, in document order — a primary action marked `:primary`. A data set — a table, a
    list, a region's state (PT-19) and a list's continuation (PT-08) — is left out whole: its rows are the data's,
    which differ between two runs, never the language's. */
export type Structure = { landmarks: string[]; headings: number[]; actions: string[] };

export type Snapshot = { where: string; language: Language; width: number; structure: Structure };

const catalogue = (language: Language): Record<string, string> =>
    JSON.parse(readFileSync(new URL(`../../lang/${language}.json`, import.meta.url), 'utf8')) as Record<string, string>;
const ID = catalogue('id');
const EN = catalogue('en');

const escape = (text: string): string => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const spaced = (text: string): string => text.replace(/\s+/g, ' ').trim();
const clip = (text: string): string => (text.length > 120 ? `${text.slice(0, 117)}…` : text);

// 1. A key shown as itself: a token equal to a key of the catalogue, or a key built from data under one of its families
// of two segments or more (`evidence.type.X`) — never a file name such as "import.csv".
const KEYS = new Set(Object.keys(ID));
const FAMILIES = new Set(
    Object.keys(ID).flatMap((key) => {
        const parts = key.split('.');

        return parts.slice(2).map((_, at) => parts.slice(0, at + 2).join('.'));
    }),
);

function shownKeys(text: string): string[] {
    return (text.match(/[A-Za-z0-9_][A-Za-z0-9_.-]*[A-Za-z0-9_]/g) ?? []).filter(
        (token) => token.includes('.') && (KEYS.has(token) || FAMILIES.has(token.slice(0, token.lastIndexOf('.')))),
    );
}

// 2. An unfilled placeholder (§3.1): a colon directly followed by an identifier starting in lower case — or a catalogue
// placeholder's `:Name`/`:NAME` form —, not inside a word, an address or a time ("10:30", "https://", "::1").
const PLACEHOLDER = /:[A-Za-z_][A-Za-z0-9_]*/g;
const PLACEHOLDER_NAMES = new Set(
    [...Object.values(ID), ...Object.values(EN)].flatMap((text) => [...text.matchAll(/:([A-Za-z_][A-Za-z0-9_]*)/g)].map((match) => (match[1] ?? '').toLowerCase())),
);
const UNFILLED = /(?<![\p{L}\p{N}_:/.\\]):([A-Za-z_][A-Za-z0-9_]*)(?![\p{L}\p{N}_])/gu;

function unfilled(text: string): string[] {
    return [...text.matchAll(UNFILLED)]
        .filter((match) => {
            const name = match[1] ?? '';

            return /^[a-z_]/.test(name) || PLACEHOLDER_NAMES.has(name.toLowerCase());
        })
        .map((match) => match[0]);
}

// 4. LOCALIZATION §3.4, encoded: business data, names and numbers are not keys, so only the catalogue's Indonesian texts
// are looked for; a key whose English is its Indonesian — a document type name, a *data* or *never translated* key of
// §6 — is never one; the issued and generated document's paper is skipped (`paperOf` below); and the approved document
// type names (`documents.doc_01`–`doc_14`, each alternative of "A / B" too) are names on either page, taken out of a text
// before it is compared. A text is Indonesian interface copy when it holds, as a whole phrase, the Indonesian text of a
// key that differs from its English one and has at least two words and eight characters with at least one word outside a
// document type name; a placeholder matches any value.
const TYPE_NAMES = [
    ...new Set(
        Object.keys(ID)
            .filter((key) => /^documents\.doc_\d+$/.test(key))
            .flatMap((key) => {
                const name = spaced(ID[key] ?? '');

                return [name, ...name.split(' / ')];
            }),
    ),
].sort((a, b) => b.length - a.length);
const NAME_MARK = '';
const TYPE_NAME = new RegExp(`(?<![\\p{L}\\p{N}])(?:${TYPE_NAMES.map(escape).join('|')})(?![\\p{L}\\p{N}])`, 'gu');
const withoutTypeNames = (text: string): string => text.replace(TYPE_NAME, NAME_MARK);
const words = (text: string): string[] => text.split(' ').filter((word) => /\p{L}/u.test(word));

type Source = { key: string; probe: string; pattern: RegExp };
const INDONESIAN: Source[] = Object.entries(ID).flatMap(([key, value]) => {
    const text = spaced(value);

    if (text === spaced(EN[key] ?? '')) {
        return [];
    }

    const literal = spaced(text.replace(PLACEHOLDER, ' '));

    if (words(literal).length < 2 || literal.length < 8 || words(withoutTypeNames(literal)).length === 0) {
        return [];
    }

    const parts = withoutTypeNames(text).split(PLACEHOLDER);
    const probe = parts.reduce((longest, part) => (part.trim().length > longest.length ? part.trim() : longest), '');

    return [{ key, probe, pattern: new RegExp(`(?<![\\p{L}\\p{N}])${parts.map(escape).join('[\\s\\S]+?')}(?![\\p{L}\\p{N}])`, 'u') }];
});

function indonesian(text: string): string[] {
    const compared = withoutTypeNames(text);

    return INDONESIAN.filter((source) => compared.includes(source.probe) && source.pattern.test(compared)).map((source) => source.key);
}

// 3. No empty string where a label belongs: the buttons, links and form controls a person reaches.
const NAMED_ROLES = ['button', 'link', 'checkbox', 'combobox', 'listbox', 'radio', 'searchbox', 'slider', 'spinbutton', 'switch', 'textbox'] as const;

// 7. A clipped label (§3.7): a box cutting or spilling its text while that whole text is one of the catalogue's texts in the
// page's language — a label; a value, such as a long name in a narrow column, may be cut by design. Each form of a counted
// string is a text of its own; a text with a placeholder holds a value and is left to the values' rule.
const forms = (value: string): string[] =>
    value
        .split('|')
        .map((form) => spaced(form.replace(/^\s*(\{\d+\}|\[\d+,\s*(\d+|\*)\])/, '')))
        .filter((form) => form !== '' && !/:[A-Za-z_]/.test(form));
const LABELS: Record<Language, Set<string>> = { id: new Set(Object.values(ID).flatMap(forms)), en: new Set(Object.values(EN).flatMap(forms)) };

type Seen = { text: string; at: string; paper: boolean };
type Reading = { texts: Seen[]; width: number; scrollWidth: number; overflow: string[]; clipped: Seen[]; structure: Structure };

/** The browser's side, serialized into the page: it closes over nothing of this module. */
async function read(delay: number): Promise<Reading> {
    await new Promise<void>((resolve) => {
        setTimeout(resolve, delay);
    });
    await document.fonts.ready;
    await new Promise<void>((resolve) => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                resolve();
            });
        });
    });

    // §3.4: the issued or generated document's paper — a draft's preview and its print copy — stays Indonesian.
    const PAPER = '[data-slot="paper-frame"], #document-print';
    const DATA = 'table, [role="table"], [role="grid"], [role="treegrid"], ul, ol, [role="list"], [role="listbox"], [role="tree"], [role="feed"], [data-region-state], [data-slot="list-continuation"]';
    const TRANSIENT = '[data-slot="toast-viewport"]';
    const READ = ['alt', 'aria-description', 'aria-label', 'aria-placeholder', 'aria-roledescription', 'aria-valuetext', 'label', 'placeholder', 'title'];
    const SECTIONING = 'article, aside, main, nav, section, [role="article"], [role="complementary"], [role="main"], [role="navigation"], [role="region"]';
    const ACTION = 'a[href], area[href], button, input[type="button"], input[type="submit"], input[type="reset"], input[type="image"], [role="button"], [role="link"]';

    const spaced = (text: string): string => text.replace(/\s+/g, ' ').trim();
    const shown = (element: Element): boolean => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden';
    const named = (element: Element): string => {
        const slot = element.getAttribute('data-slot');

        return `${element.localName}${element.id === '' ? '' : `#${element.id}`}${slot === null ? '' : `[data-slot="${slot}"]`}`;
    };
    const within = (element: Element, selector: string): boolean => (element.parentElement?.closest(selector) ?? null) !== null;
    const labelled = (element: Element): boolean => ['aria-label', 'aria-labelledby', 'title'].some((attribute) => (element.getAttribute(attribute) ?? '').trim() !== '');
    const explicit = (element: Element): string | null => {
        const role = (element.getAttribute('role') ?? '').trim().split(/\s+/)[0] ?? '';

        return role === '' ? null : role;
    };
    const implicit = (element: Element): string | null => {
        switch (element.localName) {
            case 'a':
            case 'area':
                return element.hasAttribute('href') ? 'link' : null;
            case 'button':
                return 'button';
            case 'input':
                return ['button', 'submit', 'reset', 'image'].includes((element as HTMLInputElement).type) ? 'button' : null;
            case 'header':
                return within(element, SECTIONING) ? null : 'banner';
            case 'footer':
                return within(element, SECTIONING) ? null : 'contentinfo';
            case 'nav':
                return 'navigation';
            case 'main':
                return 'main';
            case 'aside':
                return 'complementary';
            case 'search':
                return 'search';
            case 'section':
                return 'region';
            case 'form':
                return 'form';
            case 'dialog':
                return (element as HTMLDialogElement).open ? 'dialog' : null;
            default:
                return null;
        }
    };
    const role = (element: Element): string | null => explicit(element) ?? implicit(element);
    const landmark = (element: Element): string | null => {
        const kind = role(element);

        switch (kind) {
            case 'banner':
            case 'navigation':
            case 'main':
            case 'complementary':
            case 'contentinfo':
            case 'search':
            case 'dialog':
            case 'alertdialog':
                return kind;
            case 'region':
            case 'form':
                return labelled(element) ? kind : null;
            default:
                return null;
        }
    };

    // The texts a person reads or hears: the rendered text nodes, a rendered select's options, the read attributes of a
    // rendered element and the document's title.
    const texts: Seen[] = [];
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const range = document.createRange();

    for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
        const parent = node.parentElement;
        const text = spaced(node.nodeValue ?? '');

        if (parent === null || text === '' || parent.closest('script, style, noscript, template, select, textarea') !== null) {
            continue;
        }

        range.selectNodeContents(node);

        if (range.getClientRects().length > 0 && getComputedStyle(parent).visibility !== 'hidden') {
            texts.push({ text, at: named(parent), paper: parent.closest(PAPER) !== null });
        }
    }

    for (const select of document.body.querySelectorAll('select')) {
        if (shown(select)) {
            for (const option of select.options) {
                const text = spaced(option.text);

                if (text !== '') {
                    texts.push({ text, at: `${named(select)} option`, paper: false });
                }
            }
        }
    }

    for (const element of document.body.querySelectorAll(READ.map((attribute) => `[${attribute}]`).join(', '))) {
        if (shown(element)) {
            for (const attribute of READ) {
                const text = spaced(element.getAttribute(attribute) ?? '');

                if (text !== '') {
                    texts.push({ text, at: `${named(element)}@${attribute}`, paper: within(element, PAPER) });
                }
            }
        }
    }

    if (spaced(document.title) !== '') {
        texts.push({ text: spaced(document.title), at: 'title', paper: false });
    }

    // 5. No horizontal scroll outside a data table (§3.7 item 1; SV-02): whatever reaches past the viewport, unless it
    // sits in a table or in a scrolling or clipping box of its own.
    const width = window.innerWidth;
    const scrollWidth = document.documentElement.scrollWidth;
    const overflow: string[] = [];

    if (scrollWidth > width + 1) {
        const clipped = (element: Element): boolean => {
            for (let parent = element.parentElement; parent !== null && parent !== document.body; parent = parent.parentElement) {
                if (getComputedStyle(parent).overflowX !== 'visible') {
                    return true;
                }
            }

            return false;
        };
        const past = Array.from(document.body.querySelectorAll('*')).filter((element) => {
            const box = element.getBoundingClientRect();

            return box.width > 0 && box.right + window.scrollX > width + 1 && !clipped(element);
        });
        const outside = past.filter((element) => element.closest('table, [role="table"]') === null);

        if (past.length === 0) {
            overflow.push('no element found past the viewport');
        }

        for (const element of outside.slice(0, 5)) {
            overflow.push(`${named(element)} reaching ${String(Math.round(element.getBoundingClientRect().right + window.scrollX))} px`);
        }
    }

    // 7. A box that cuts its text — an ellipsis, a hidden or clipped overflow, a line clamp — or a label's own box — a
    // heading, a button, a tab, a label, a table's header — whose text is wider than the box, or taller under a clamp.
    // Whether the text is a label is the catalogue's to say, outside. A visually hidden text — a box of a pixel or two, or
    // clipped away — is no label anyone sees.
    const LABEL_BOX = 'h1, h2, h3, h4, h5, h6, button, [role="button"], [role="tab"], [role="menuitem"], label, legend, th';
    const clipped: Seen[] = [];

    for (const element of document.body.querySelectorAll('*')) {
        const style = getComputedStyle(element);
        const clamp = style.getPropertyValue('-webkit-line-clamp');
        const clamped = clamp !== '' && clamp !== 'none';
        const cuts = style.textOverflow === 'ellipsis' || style.overflowX === 'hidden' || style.overflowX === 'clip' || clamped;

        if (!cuts && !element.matches(LABEL_BOX)) {
            continue;
        }

        const box = element.getBoundingClientRect();

        const clippedAway = !['', 'auto'].includes(style.getPropertyValue('clip')) || style.getPropertyValue('clip-path').startsWith('inset(50%');

        if (box.width <= 2 || box.height <= 2 || clippedAway || !shown(element) || element.closest(TRANSIENT) !== null) {
            continue;
        }

        if (element.scrollWidth > element.clientWidth + 1 || (clamped && element.scrollHeight > element.clientHeight + 1)) {
            const text = spaced(element.textContent);

            if (text !== '') {
                clipped.push({ text, at: named(element), paper: element.closest(PAPER) !== null });
            }
        }
    }

    // 6. The structure.
    const konten = document.getElementById('konten');
    const scopes: Element[] = konten !== null && shown(konten) ? [konten] : [];

    for (const dialog of document.body.querySelectorAll('[role="dialog"], [role="alertdialog"], dialog[open]')) {
        if (shown(dialog) && !scopes.some((scope) => scope.contains(dialog))) {
            scopes.push(dialog);
        }
    }

    const counted = (element: Element, scope: Element): boolean => {
        const data = element.parentElement?.closest(DATA) ?? null;

        return shown(element) && (data === null || !scope.contains(data));
    };
    const landmarks = Array.from(document.body.querySelectorAll('header, footer, nav, main, aside, search, section, form, dialog, [role]'))
        .filter((element) => shown(element) && element.closest(TRANSIENT) === null && !within(element, DATA))
        .map(landmark)
        .filter((kind): kind is string => kind !== null);
    const headings = scopes.flatMap((scope) =>
        Array.from(scope.querySelectorAll('h1, h2, h3, h4, h5, h6, [role="heading"]'))
            .filter((element) => counted(element, scope))
            .map((element) => {
                const level = Number(element.getAttribute('aria-level'));

                return Number.isInteger(level) && level > 0 ? level : Number(/^h([1-6])$/.exec(element.localName)?.[1] ?? '2');
            }),
    );
    const actions = scopes.flatMap((scope) =>
        Array.from(scope.querySelectorAll(ACTION))
            .filter((element) => counted(element, scope))
            .flatMap((element) => {
                const kind = role(element);

                if (kind !== 'button' && kind !== 'link') {
                    return [];
                }

                return [element.classList.contains('bg-primary') || element.classList.contains('bg-lime-cta') ? `${kind}:primary` : kind];
            }),
    );

    return { texts, width, scrollWidth, overflow, clipped, structure: { landmarks, headings, actions } };
}

/** The page once it holds still: its structure, its width and its number of texts the same in two readings. */
async function settled(page: Page): Promise<Reading> {
    const sign = (reading: Reading): string => JSON.stringify([reading.structure, reading.scrollWidth, reading.texts.length]);
    let last = await page.evaluate(read, 0);

    for (let attempt = 0; attempt < 12; attempt += 1) {
        const next = await page.evaluate(read, 250);

        if (sign(next) === sign(last)) {
            return next;
        }

        last = next;
    }

    return last;
}

/**
 * Audits the page as rendered in `language` — rules 1–5, every failure listed in one assertion — and returns its
 * structure for the comparison of the two languages (rule 6), never asserting it here.
 */
export async function audit(page: Page, language: Language, where: string): Promise<Snapshot> {
    await expect(page.locator('html'), `${where}: the page is in ${language}`).toHaveAttribute('lang', language);
    const reading = await settled(page);
    const width = page.viewportSize()?.width ?? reading.width;
    const problems = new Set<string>();

    for (const seen of reading.texts) {
        const keys = shownKeys(seen.text);
        const holes = unfilled(seen.text);

        if (keys.length > 0) {
            problems.add(`a key shown as itself (${keys.join(', ')}) in ${seen.at}: "${clip(seen.text)}"`);
        }

        if (holes.length > 0) {
            problems.add(`an unfilled placeholder (${holes.join(', ')}) in ${seen.at}: "${clip(seen.text)}"`);
        }

        if (language === 'en' && !seen.paper) {
            const sources = indonesian(seen.text);

            if (sources.length > 0) {
                problems.add(`an Indonesian interface string (${sources.slice(0, 3).join(', ')}) in ${seen.at}: "${clip(seen.text)}"`);
            }
        }
    }

    for (const kind of NAMED_ROLES) {
        const nameless = await page.getByRole(kind, { name: '', exact: true }).evaluateAll((elements) => elements.map((element) => element.outerHTML.slice(0, 160)));

        for (const html of nameless) {
            problems.add(`a ${kind} without a name: ${html}`);
        }
    }

    for (const offender of reading.overflow) {
        problems.add(`a horizontal scroll outside a data table, the page ${String(reading.scrollWidth)} px wide in ${String(reading.width)} px: ${offender}`);
    }

    for (const seen of reading.clipped) {
        if (!seen.paper && LABELS[language].has(seen.text)) {
            problems.add(`a clipped label in ${seen.at}: "${clip(seen.text)}"`);
        }
    }

    // 8. Targets at their sizes (§3.7; DESIGN_SYSTEM UXV-10, D-DS-19): WCAG 2.5.8 as the accessibility layer's engine
    // applies it — 24 by 24 CSS px with the suite's fine pointer, its spacing and inline exceptions included — in the
    // language and at the width rendered.
    for (const violation of (await new AxeBuilder({ page }).withRules(['target-size']).analyze()).violations) {
        for (const node of violation.nodes) {
            problems.add(`a target under its size (${violation.id}) at ${node.target.join(' ')}: ${node.html.slice(0, 160)}`);
        }
    }

    expect([...problems], `${where} in ${language} at ${String(width)} px (LOCALIZATION §3.6, §3.7)`).toEqual([]);

    return { where, language, width, structure: reading.structure };
}

// The first snapshot of each page at each width in this worker — a spec file's tests run in order in one worker, its
// Indonesian test before its English one —, against which every later render of the same page is compared.
const first = new Map<string, Snapshot>();

/** Records a snapshot and, once the same page at the same width was recorded before in this spec file and project,
    asserts the same structure: the same regions and actions in the same order, the same primary action (§3.7). */
export function expectSameStructure(snapshot: Snapshot): void {
    const info = test.info();
    const key = [info.project.name, info.file, snapshot.where, String(snapshot.width)].join(' | ');
    const earlier = first.get(key);

    if (earlier === undefined) {
        first.set(key, snapshot);

        return;
    }

    expect(snapshot.structure, `${snapshot.where} at ${String(snapshot.width)} px: the same regions and actions in the same order in ${earlier.language} and ${snapshot.language} (LOCALIZATION §3.7)`).toEqual(earlier.structure);
}

/** Audits the page at the current width and at the phone's, the viewport restored after, comparing each structure with
    the other language's render of the same page; `phone: false` where a resize would disturb the spec's flow. */
export async function auditPage(page: Page, language: Language, where: string, { phone = true }: { phone?: boolean } = {}): Promise<void> {
    const info = test.info();

    if (info.timeout > 0) {
        info.setTimeout(info.timeout + (phone ? 30_000 : 15_000));
    }

    expectSameStructure(await audit(page, language, where));

    if (!phone) {
        return;
    }

    const size = page.viewportSize();
    await page.setViewportSize(PHONE);

    try {
        expectSameStructure(await audit(page, language, where));
    } finally {
        if (size !== null) {
            await page.setViewportSize(size);
        }
    }
}
