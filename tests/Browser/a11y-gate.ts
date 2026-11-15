import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';

import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

// TASK-066 (TEST_STRATEGY §11; DESIGN_SYSTEM §15; TL-12): the accessibility audit on the localization audit's own visit of
// every page state — one visit per state, no audit of its own —: axe-core's WCAG 2.2 AA rules on each state at the
// desktop and the phone widths on Chromium and WebKit, a violation failing the run (criterion 1); and once per page on
// Chromium the measured checks of DESIGN_SYSTEM §15 — focus walked by the keyboard, visible and never wholly hidden by a
// sticky or fixed region (2.4.11), no trap and no positive tab index (2.1.2), movement under reduced motion (D-DS-17),
// the focus ring under forced colours, the control boundaries' 3:1 (1.4.11), one `h1`, the skip link, a live region,
// each status a text. Each reading is recorded beside the browser evidence; the measured checks never fail the visit —
// their findings go to the Owner with their evidence (TASK-065's model; DIR-124).

export const AA = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

const ROOT = join(process.cwd(), 'storage/test-results/browser/a11y');
const scanned = new Set<string>();
const measured = new Set<string>();

type Violation = { id: string; impact: string | null; help: string; nodes: number; targets: string[] };
type Walk = { focusables: number; walked: number; wrapped: boolean; invisible: string[]; obscured: string[]; trapped: string | null; positiveTabIndex: string[] };
type Static = { h1: number; skipLink: boolean; liveRegions: number; boundaries: string[]; statusesWithoutText: string[] };
type Motion = { moving: string[] };
type Forced = { focused: string | null; ringLost: boolean | null; optedOut: string[] };

/** The audit of one page state at the current width, in the audit's language. */
export async function accessibility(page: Page, where: string): Promise<void> {
    const info = test.info();
    const engine = info.project.name === 'webkit' ? 'webkit' : 'chromium';
    const id = /PG-\d{3}/.exec(where)?.[0];
    const width = page.viewportSize()?.width ?? 0;
    const key = `${engine} ${where} ${String(width)}`;

    // The audit's fixture places are no page of the 65; a state is scanned once per engine and width.
    if (id === undefined || scanned.has(key)) {
        return;
    }

    scanned.add(key);

    if (info.timeout > 0) {
        info.setTimeout(info.timeout + 60_000);
    }

    const errors: string[] = [];
    const attempt = async <T>(what: string, run: () => Promise<T>): Promise<T | null> => {
        try {
            return await run();
        } catch (error) {
            errors.push(`${what}: ${error instanceof Error ? (error.message.split('\n')[0] ?? '') : String(error)}`);

            return null;
        }
    };

    const results = await new AxeBuilder({ page }).withTags(AA).analyze();
    const violations: Violation[] = results.violations.map((violation) => ({
        id: violation.id,
        impact: violation.impact ?? null,
        help: violation.help,
        nodes: violation.nodes.length,
        targets: violation.nodes.slice(0, 3).map((node) => `${node.target.join(' ')}: ${node.html.slice(0, 120)}`),
    }));

    // Once per page on Chromium, at each width: the measured checks.
    const once = engine === 'chromium' && !measured.has(`${id} ${String(width)}`);
    let walk: Walk | null = null;
    let statics: Static | null = null;
    let motion: Motion | null = null;
    let forced: Forced | null = null;

    if (once) {
        measured.add(`${id} ${String(width)}`);
        statics = await attempt('static', () => page.evaluate(readStatic));
        walk = await attempt('focus walk', () => walkFocus(page));

        if (width > 768) {
            motion = await attempt('reduced motion', () => reducedMotion(page));
            forced = await attempt('forced colours', () => forcedColours(page));
        }
    }

    const file = join(ROOT, engine, `${where.replace(/[^A-Za-z0-9-]+/g, '-')}-${String(width)}.json`);
    mkdirSync(dirname(file), { recursive: true });
    writeFileSync(file, `${JSON.stringify({ where, page: id, engine, width, url: page.url(), violations, passes: results.passes.length, statics, walk, motion, forced, errors }, null, 2)}\n`);

    // Criterion 1: a violation of an AA criterion the engine decides fails the run — softly, so every state is still read.
    expect.soft(violations, `${where} at ${String(width)} px on ${engine}: no WCAG 2.2 AA violation (TEST_STRATEGY §11; TL-12)`).toEqual([]);
}

/** The page's structure and its static checks (§15). */
function readStatic(): Static {
    const visible = (element: Element): boolean => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden';
    const channel = (value: number): number => {
        const c = value / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    const parse = (colour: string): [number, number, number, number] | null => {
        const match = /rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)/.exec(colour);

        return match === null ? null : [Number(match[1]), Number(match[2]), Number(match[3]), match[4] === undefined ? 1 : Number(match[4])];
    };
    const luminance = ([r, g, b]: [number, number, number, number]): number => 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
    const ratio = (a: [number, number, number, number], b: [number, number, number, number]): number => {
        const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x);

        return ((high ?? 0) + 0.05) / ((low ?? 0) + 0.05);
    };
    // The colour a control stands on: the nearest ancestor with an opaque background.
    const behind = (element: Element): [number, number, number, number] => {
        for (let node = element.parentElement; node !== null; node = node.parentElement) {
            const colour = parse(getComputedStyle(node).backgroundColor);

            if (colour !== null && colour[3] >= 0.99) {
                return colour;
            }
        }

        return [255, 255, 255, 1];
    };
    const describe = (element: Element): string => `${element.tagName.toLowerCase()}${element.getAttribute('data-slot') !== null ? `[data-slot=${element.getAttribute('data-slot') ?? ''}]` : ''} «${(element.getAttribute('aria-label') ?? element.textContent).replace(/\s+/g, ' ').trim().slice(0, 40)}»`;

    // 1.4.11: a text field's, a select's and a check's boundary — its border, or its fill where it has no border — at 3:1.
    const boundaries: string[] = [];

    for (const control of Array.from(document.querySelectorAll('input:not([type="hidden"]), select, textarea, [role="checkbox"], [role="radio"], [role="switch"], [data-slot="select-trigger"]'))) {
        if (!visible(control) || control.closest('.paper') !== null) {
            continue;
        }

        const style = getComputedStyle(control);

        if (style.clipPath === 'inset(50%)' || Number.parseFloat(style.opacity) === 0) {
            continue;
        }

        const ground = behind(control);
        const border = Number.parseFloat(style.borderTopWidth) > 0 ? parse(style.borderTopColor) : null;
        const fill = parse(style.backgroundColor);
        const best = Math.max(border === null ? 1 : ratio(border, ground), fill === null || fill[3] < 0.99 ? 1 : ratio(fill, ground));

        if (best < 3) {
            boundaries.push(`${describe(control)} ${best.toFixed(2)}:1`);
        }
    }

    const statusesWithoutText = Array.from(document.querySelectorAll('[data-slot="badge"]'))
        .filter((badge) => visible(badge) && badge.textContent.trim() === '')
        .map(describe);

    return {
        h1: Array.from(document.querySelectorAll('h1')).filter(visible).length,
        skipLink: document.querySelector('a[href="#konten"]') !== null,
        liveRegions: document.querySelectorAll('[aria-live], [role="status"], [role="alert"], [role="log"]').length,
        boundaries: boundaries.slice(0, 12),
        statusesWithoutText: statusesWithoutText.slice(0, 12),
    };
}

/**
 * The keyboard's walk from the page's top: up to 60 Tab presses, each focused element's ring visible (2.4.7), never wholly
 * under a fixed or sticky region (2.4.11), no element twice before the walk wraps unless a dialog holds it (2.1.2), and no
 * positive tab index. Focus and scroll are put back after.
 */
async function walkFocus(page: Page): Promise<Walk> {
    const positiveTabIndex = await page.evaluate(() =>
        Array.from(document.querySelectorAll('[tabindex]')).filter((element) => Number(element.getAttribute('tabindex')) > 0).map((element) => element.outerHTML.slice(0, 100)),
    );
    const focusables = await page.evaluate(() =>
        Array.from(document.querySelectorAll<HTMLElement>('a[href], button, input, select, textarea, [tabindex]')).filter((element) => element.tabIndex >= 0 && !element.hasAttribute('disabled') && element.getClientRects().length > 0).length,
    );
    await startAtTop(page);

    const invisible: string[] = [];
    const obscured: string[] = [];
    const seen: string[] = [];
    let wrapped = false;
    let trapped: string | null = null;
    let walked = 0;

    try {
        for (let step = 0; step < 60; step += 1) {
            await page.keyboard.press('Tab');
            const reading = await page.evaluate((index) => {
                const active = document.activeElement;

                if (active === null || active === document.body || active === document.documentElement) {
                    return null;
                }

                const element = active as HTMLElement;
                element.dataset.a11yWalk ??= String(index);
                const style = getComputedStyle(element);
                const ring = (style.outlineStyle !== 'none' && Number.parseFloat(style.outlineWidth) >= 2) || /\b\d+(\.\d+)?px\b/.test(style.boxShadow.replace(/rgba?\([^)]*\)/g, ''));
                const box = element.getBoundingClientRect();
                // Wholly hidden: every probe point of the element answered by a fixed or sticky region that is not its own.
                const points: [number, number][] = [[box.left + 2, box.top + 2], [box.right - 2, box.top + 2], [box.left + 2, box.bottom - 2], [box.right - 2, box.bottom - 2], [box.left + box.width / 2, box.top + box.height / 2]];
                const covered = box.width > 0 && box.height > 0 && points.every(([x, y]) => {
                    const hit = document.elementFromPoint(x, y);

                    if (hit === null || hit === element || element.contains(hit) || hit.contains(element)) {
                        return x < 0 || y < 0 || x > window.innerWidth || y > window.innerHeight;
                    }

                    for (let node: Element | null = hit; node !== null; node = node.parentElement) {
                        const position = getComputedStyle(node).position;

                        if (position === 'fixed' || position === 'sticky') {
                            return !node.contains(element);
                        }
                    }

                    return false;
                });
                const name = `${element.tagName.toLowerCase()}${element.getAttribute('data-slot') !== null ? `[data-slot=${element.getAttribute('data-slot') ?? ''}]` : ''} «${(element.getAttribute('aria-label') ?? element.textContent).replace(/\s+/g, ' ').trim().slice(0, 40)}»`;

                return { mark: element.dataset.a11yWalk, name, ring, covered, inDialog: element.closest('[role="dialog"], [role="alertdialog"]') !== null };
            }, step);

            if (reading === null) {
                wrapped = seen.length > 0;

                if (wrapped) {
                    break;
                }

                continue;
            }

            walked += 1;

            if (!reading.ring) {
                invisible.push(reading.name);
            }

            if (reading.covered) {
                obscured.push(reading.name);
            }

            if (seen.includes(reading.mark)) {
                // Back at an element already passed without the page wrapping: a loop, which only a dialog may hold.
                if (!reading.inDialog && seen.length < focusables) {
                    trapped = reading.name;
                }

                wrapped = true;
                break;
            }

            seen.push(reading.mark);
        }
    } finally {
        await page.evaluate(() => {
            (document.activeElement as HTMLElement | null)?.blur();
            document.getElementById('a11y-walk-start')?.remove();
            for (const element of Array.from(document.querySelectorAll<HTMLElement>('[data-a11y-walk]'))) {
                delete element.dataset.a11yWalk;
            }
            window.scrollTo(0, 0);
        });
    }

    return { focusables, walked, wrapped, invisible: [...new Set(invisible)].slice(0, 12), obscured: [...new Set(obscured)].slice(0, 12), trapped, positiveTabIndex };
}

/**
 * The keyboard's next Tab starts from the document's top: an element out of the tab order focused first at the body's
 * start — a blur alone leaves the sequential starting point where the last focus was.
 */
async function startAtTop(page: Page): Promise<void> {
    await page.evaluate(() => {
        document.getElementById('a11y-walk-start')?.remove();
        const start = document.createElement('span');
        start.id = 'a11y-walk-start';
        start.tabIndex = -1;
        document.body.prepend(start);
        start.focus({ preventScroll: true });
        window.scrollTo(0, 0);
    });
}

/** D-DS-17: under reduced motion, no declared movement and no transition or animation longer than a 150 ms crossfade. */
async function reducedMotion(page: Page): Promise<Motion> {
    await page.emulateMedia({ reducedMotion: 'reduce' });

    try {
        const moving = await page.evaluate(() => {
            const seconds = (value: string): number => Math.max(0, ...value.split(',').map((part) => (part.trim().endsWith('ms') ? Number.parseFloat(part) / 1000 : Number.parseFloat(part) || 0)));
            const found: string[] = [];

            for (const element of Array.from(document.body.querySelectorAll('*'))) {
                if (element.getClientRects().length === 0) {
                    continue;
                }

                const style = getComputedStyle(element);
                const transition = seconds(style.transitionDuration);
                const animation = style.animationName === 'none' ? 0 : seconds(style.animationDuration);
                const movement = /transform|translate|scale|rotate|top|left|right|bottom|margin|height|width|inset/.test(style.transitionProperty) && transition > 0;

                if (transition > 0.15 || animation > 0.15 || movement) {
                    found.push(`${element.tagName.toLowerCase()}${element.getAttribute('data-slot') !== null ? `[data-slot=${element.getAttribute('data-slot') ?? ''}]` : ''} transition ${style.transitionProperty} ${style.transitionDuration}${animation > 0 ? `, animation ${style.animationName} ${style.animationDuration}` : ''}`);
                }
            }

            return [...new Set(found)].slice(0, 12);
        });

        return { moving };
    } finally {
        await page.emulateMedia({ reducedMotion: null });
    }
}

/** Forced colours: the focus ring kept on a focused control, and the elements that opt out of the system's colours. */
async function forcedColours(page: Page): Promise<Forced> {
    await page.emulateMedia({ forcedColors: 'active' });

    try {
        await startAtTop(page);
        await page.keyboard.press('Tab');

        return await page.evaluate(() => {
            document.getElementById('a11y-walk-start')?.remove();
            const active = document.activeElement;
            // Read now: a computed style is live, and the blur below would take the ring away.
            const ringLost = active === null || active === document.body ? null : getComputedStyle(active).outlineStyle === 'none';
            const optedOut = Array.from(document.body.querySelectorAll('*'))
                .filter((element) => element.getClientRects().length > 0 && getComputedStyle(element).getPropertyValue('forced-color-adjust') === 'none')
                .map((element) => `${element.tagName.toLowerCase()}.${(element.getAttribute('class') ?? '').split(/\s+/).slice(0, 3).join('.')}`);
            const focused = active === null || active === document.body ? null : `${active.tagName.toLowerCase()} «${(active.getAttribute('aria-label') ?? active.textContent).replace(/\s+/g, ' ').trim().slice(0, 40)}»`;
            (document.activeElement as HTMLElement | null)?.blur();

            return { focused, ringLost, optedOut: [...new Set(optedOut)].slice(0, 12) };
        });
    } finally {
        await page.emulateMedia({ forcedColors: null });
        await page.evaluate(() => {
            window.scrollTo(0, 0);
        });
    }
}
