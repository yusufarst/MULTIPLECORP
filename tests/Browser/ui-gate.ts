import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';

import { test, type Page } from '@playwright/test';

// TASK-065 (AICWDF §27; TEST_STRATEGY §9; DESIGN_SYSTEM §§5–6, §9.4, §12; APPR-038): the UI/UX consistency gate, judged
// on the built page during the localization audit's own visit — one visit per page —, its record written beside the
// browser evidence. It measures and records; it never fails the visit: each finding goes to the Owner with its evidence
// (APPR-038). The responsive matrix runs at all ten band edges on Chromium and at three on WebKit, the others NOT TESTED.

export const BAND_EDGES = { chromium: [320, 390, 767, 768, 1024, 1025, 1200, 1279, 1280, 1680], webkit: [390, 768, 1280] } as const;

// The suite runs from the repository's root, locally and in CI (`docker compose run --rm e2e`).
const ROOT = join(process.cwd(), 'storage/test-results/browser/gate');
const judged = new Set<string>();
const touched = new Set<string>();

type Result = 'PASS' | 'FAIL' | 'N/A' | 'NOT TESTED' | 'REVIEW' | 'ELSEWHERE';
type Item = { item: string; result: Result; detail: string };
type Width = { width: number; horizontalScroll: string[]; clippedCopy: string[] };
type Tokens = { offToken: Record<string, { value: string; count: number; sample: string }[]> };
type Actions = { primaryPerRegion: Record<string, number>; duplicates: { region: string; role: string; name: string; count: number }[] };
type States = { regions: string[]; emptyShown: boolean };

/** The gate of one page, once per engine and place — the Indonesian visit of the localization audit. */
export async function gate(page: Page, where: string): Promise<void> {
    const info = test.info();
    const engine = info.project.name === 'webkit' ? 'webkit' : 'chromium';
    const key = `${engine} ${where}`;

    if (judged.has(key)) {
        return;
    }

    judged.add(key);

    if (info.timeout > 0) {
        info.setTimeout(info.timeout + 120_000);
    }

    const id = /PG-\d{3}/.exec(where)?.[0] ?? where;
    const size = page.viewportSize();
    const errors: string[] = [];
    const attempt = async <T>(what: string, run: () => Promise<T>, fallback: T): Promise<T> => {
        try {
            return await run();
        } catch (error) {
            errors.push(`${what}: ${error instanceof Error ? (error.message.split('\n')[0] ?? '') : String(error)}`);

            return fallback;
        }
    };

    const tokens = await attempt('tokens', () => page.evaluate(measureTokens), { offToken: {} });
    const actions = await attempt('actions', () => page.evaluate(measureActions), { primaryPerRegion: {}, duplicates: [] });
    const states = await attempt('states', () => page.evaluate(measureStates), { regions: [], emptyShown: false });
    const widths: Width[] = [];

    try {
        for (const width of BAND_EDGES[engine]) {
            await page.setViewportSize({ width, height: 900 });
            await page.evaluate(() => new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done))));
            widths.push(await attempt(`width ${String(width)}`, () => page.evaluate(measureWidth), { width, horizontalScroll: [], clippedCopy: [] }));

            if (engine === 'chromium' && (width === 390 || width === 1280) && !touched.has(`${id} ${String(width)}`)) {
                touched.add(`${id} ${String(width)}`);
                await attempt(`screenshot ${String(width)}`, () => page.screenshot({ path: join(ROOT, 'screens', `${id}-${String(width)}.png`), fullPage: true }), undefined);
            }
        }
    } finally {
        if (size !== null) {
            await page.setViewportSize(size);
        }
    }

    let targets: { name: string; width: number; height: number }[] | null = null;

    if (engine === 'chromium' && !touched.has(`${id} coarse`)) {
        touched.add(`${id} coarse`);
        targets = await attempt('coarse targets', () => coarseTargets(page), null);
    }
    const offToken = Object.values(tokens.offToken).reduce((sum, values) => sum + values.length, 0);
    const spacingOff = (tokens.offToken.spacing ?? []).length;
    const crowded = Object.values(actions.primaryPerRegion).filter((count) => count > 1).length;
    const scrolls = widths.filter((reading) => reading.horizontalScroll.length > 0);
    const clipped = widths.filter((reading) => reading.clippedCopy.length > 0);
    const small = targets === null ? null : targets.filter((target) => target.height < 44 || target.width < 44);
    const items: Item[] = [
        { item: 'Modern visual quality', result: 'REVIEW', detail: 'SLOP-01–SLOP-45 reviewed on the screenshots of this page (TASK-065 review record)' },
        { item: 'Design system consistency', result: offToken === 0 ? 'PASS' : 'FAIL', detail: offToken === 0 ? 'every computed colour, type, radius, elevation, motion and spacing value a token' : `${String(offToken)} distinct values outside the tokens (offToken)` },
        { item: 'Primary action clear', result: crowded === 0 ? 'PASS' : 'FAIL', detail: crowded === 0 ? 'at most one primary action in each region' : `${String(crowded)} regions with more than one primary action` },
        { item: 'Unnecessary duplicate actions', result: actions.duplicates.length === 0 ? 'PASS' : 'FAIL', detail: `${String(actions.duplicates.length)} duplicated actions in a region, outside rows and menus — each to be read against its INT- contract` },
        { item: 'Back action clear', result: 'ELSEWHERE', detail: 'judged by the NAV- paths\' tests (TEST_STRATEGY §8; TL-09)' },
        { item: 'Navigation contract valid', result: 'ELSEWHERE', detail: 'judged by the NAV- paths\' tests (TEST_STRATEGY §8; TL-09)' },
        { item: 'Indonesian default', result: 'ELSEWHERE', detail: 'TASK-067 (TEST_STRATEGY §10), entered by TASK-070' },
        { item: 'Plain Indonesian', result: 'ELSEWHERE', detail: 'TASK-067 (TEST_STRATEGY §10), entered by TASK-070' },
        { item: 'English available', result: 'ELSEWHERE', detail: 'TASK-067 (TEST_STRATEGY §10), entered by TASK-070' },
        { item: 'Missing translations: 0', result: 'ELSEWHERE', detail: 'TASK-067 (TEST_STRATEGY §10), entered by TASK-070' },
        { item: 'Language switch', result: 'ELSEWHERE', detail: 'TASK-067 (TEST_STRATEGY §10), entered by TASK-070' },
        { item: 'Mobile-first', result: scrolls.some((reading) => reading.width <= 390) ? 'FAIL' : 'PASS', detail: 'the phone composition at 320 and 390 px without horizontal page scrolling outside a table frame' },
        { item: 'Responsive', result: scrolls.length === 0 && clipped.length === 0 ? 'PASS' : 'FAIL', detail: `${BAND_EDGES[engine].join(', ')} px on ${engine}${engine === 'webkit' ? ' — the other band edges NOT TESTED (APPR-038)' : ''}; ${String(scrolls.length)} widths with horizontal scrolling, ${String(clipped.length)} with mandated copy cut under 1.4.12's text spacing` },
        { item: 'Adaptive', result: small === null ? 'NOT TESTED' : small.length === 0 ? 'PASS' : 'FAIL', detail: small === null ? (engine === 'webkit' ? 'the coarse pointer measured on Chromium' : 'the coarse pointer not reached on this visit') : `${String(small.length)} controls below 44 px under a coarse pointer at 390 px` },
        { item: 'Loading state', result: states.regions.length > 0 ? 'PASS' : 'N/A', detail: states.regions.length > 0 ? `deferred regions settled: ${states.regions.join(', ')}` : 'no deferred group on this page' },
        { item: 'Empty state', result: states.emptyShown ? 'PASS' : 'NOT TESTED', detail: states.emptyShown ? 'an empty state shown with its title' : 'not reached on this visit\'s data' },
        { item: 'Error state', result: 'NOT TESTED', detail: 'an outcome\'s error is judged by the INT- tests (TL-08), not on this visit' },
        { item: 'Destructive action safety', result: 'ELSEWHERE', detail: 'IP-08\'s confirmation judged with each INT- contract (TL-08)' },
        { item: 'Accessibility critical checks', result: 'ELSEWHERE', detail: 'TASK-066 (TEST_STRATEGY §11), entered by TASK-070' },
        { item: 'Visual breathing room', result: spacingOff === 0 ? 'PASS' : 'FAIL', detail: spacingOff === 0 ? 'every measured spacing a token' : `${String(spacingOff)} spacing values outside the tokens` },
        { item: 'Content density appropriate', result: 'REVIEW', detail: 'reviewed on the screenshots against OC-09 and AICWDF §18.2 (TASK-065 review record)' },
    ];
    const file = join(ROOT, engine, `${where.replace(/[^A-Za-z0-9-]+/g, '-')}.json`);
    mkdirSync(dirname(file), { recursive: true });
    writeFileSync(file, `${JSON.stringify({ where, page: id, engine, url: page.url(), items, tokens, actions, states, widths, targets, errors }, null, 2)}\n`);
}

/** The controls under a coarse pointer at 390 px, in a touch context of the same session. */
async function coarseTargets(page: Page): Promise<{ name: string; width: number; height: number }[]> {
    const browser = page.context().browser();

    if (browser === null) {
        return [];
    }

    const context = await browser.newContext({ storageState: await page.context().storageState(), hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 }, ignoreHTTPSErrors: true });

    try {
        const touch = await context.newPage();
        await touch.goto(page.url(), { waitUntil: 'load' });

        return await touch.evaluate(() => Array.from(document.querySelectorAll<HTMLElement>('button, a[href], input:not([type="hidden"]), select, textarea, [role="button"], [role="tab"], [role="checkbox"], [role="switch"]'))
            .filter((element) => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden' && !element.closest('p, li > a:only-child'))
            .map((element) => {
                const box = element.getBoundingClientRect();

                return { name: (element.getAttribute('aria-label') ?? (element.textContent.trim() || (element.getAttribute('name') ?? element.tagName))).trim().slice(0, 60), width: Math.round(box.width), height: Math.round(box.height) };
            })
            // A visually hidden control — a native box under a drawn one — is not a target of its own.
            .filter((target) => target.width > 2 && target.height > 2));
    } finally {
        await context.close();
    }
}

/** DESIGN_SYSTEM §5's tokens resolved in the page, and every visible element's computed values outside them. */
function measureTokens(): Tokens {
    const names = new Set<string>();
    const walk = (rules: CSSRuleList): void => {
        for (const rule of Array.from(rules)) {
            if (rule instanceof CSSStyleRule && /:root|:host/.test(rule.selectorText)) {
                for (const property of Array.from(rule.style)) {
                    if (property.startsWith('--')) {
                        names.add(property);
                    }
                }
            }

            if ('cssRules' in rule && rule.cssRules instanceof CSSRuleList) {
                walk(rule.cssRules);
            }
        }
    };

    for (const sheet of Array.from(document.styleSheets)) {
        try {
            walk(sheet.cssRules);
        } catch {
            // A stylesheet of another origin is not read.
        }
    }

    const probe = document.createElement('div');
    document.body.appendChild(probe);
    const canvas = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    // A colour's sRGB channels and alpha, read back from one painted pixel.
    const rgb = (value: string): [number, number, number, number] | null => {
        if (canvas === null || value === '' || value === 'transparent' || /rgba?\(0, 0, 0, 0\)/.test(value)) {
            return null;
        }

        canvas.clearRect(0, 0, 1, 1);
        canvas.fillStyle = '#000';
        canvas.fillStyle = value;
        canvas.fillRect(0, 0, 1, 1);
        const [r = 0, g = 0, b = 0, a = 0] = Array.from(canvas.getImageData(0, 0, 1, 1).data);

        return a === 0 ? null : [r, g, b, a];
    };
    const resolved = (property: string, name: string): string => {
        probe.style.setProperty(property, `var(${name})`);
        const value = getComputedStyle(probe).getPropertyValue(property);
        probe.style.removeProperty(property);

        return value;
    };
    const tokens = Array.from(names);
    const px = (value: string): number | null => (/^-?[\d.]+px$/.test(value) ? Math.abs(Number.parseFloat(value)) : null);
    const allowed = {
        colour: tokens.filter((name) => name.startsWith('--color-')).map((name) => rgb(resolved('color', name))).filter((value): value is [number, number, number, number] => value !== null),
        size: new Set(tokens.filter((name) => /^--(text|m)-/.test(name) && !/line-height|letter-spacing|weight|tracking/.test(name)).map((name) => px(resolved('font-size', name))).filter((value): value is number => value !== null)),
        weight: new Set(tokens.filter((name) => name.startsWith('--font-weight-') || /weight/.test(name)).map((name) => resolved('font-weight', name)).filter((value) => value !== '')),
        radius: new Set(tokens.filter((name) => name.startsWith('--radius')).map((name) => px(resolved('border-top-left-radius', name))).filter((value): value is number => value !== null)),
        shadow: new Set(tokens.filter((name) => name.startsWith('--shadow')).map((name) => resolved('box-shadow', name)).filter((value) => value !== '' && value !== 'none')),
        spacing: new Set(tokens.filter((name) => /^--(spacing|space|control|sidebar|nav|icon)/.test(name)).map((name) => px(resolved('padding-top', name))).filter((value): value is number => value !== null)),
        motion: new Set(tokens.filter((name) => /dur/.test(name)).map((name) => resolved('transition-duration', name)).filter((value) => value !== '')),
    };
    probe.remove();

    const found: Record<string, Map<string, { count: number; sample: string }>> = {};
    const note = (group: string, value: string, element: Element): void => {
        const bucket = (found[group] ??= new Map());
        const entry = bucket.get(value) ?? { count: 0, sample: `${element.tagName.toLowerCase()}${element.getAttribute('data-slot') !== null ? `[data-slot=${element.getAttribute('data-slot') ?? ''}]` : ''}.${(element.getAttribute('class') ?? '').split(/\s+/).slice(0, 4).join('.')}` };
        entry.count += 1;
        bucket.set(value, entry);
    };
    const near = (set: Set<number>, value: number): boolean => value === 0 || value >= 999 || Array.from(set).some((token) => Math.abs(token - value) <= 0.5);
    // An opaque colour within rounding of a token; a translucent one — a token at an opacity — within its looser read-back.
    const tokenColour = ([r, g, b, a]: [number, number, number, number]): boolean => {
        const tolerance = a === 255 ? 2 : 12;

        return allowed.colour.some(([tr, tg, tb]) => Math.abs(tr - r) <= tolerance && Math.abs(tg - g) <= tolerance && Math.abs(tb - b) <= tolerance);
    };

    for (const element of Array.from(document.body.querySelectorAll('*'))) {
        if (element.getClientRects().length === 0 || element.closest('svg') !== null || element === probe) {
            continue;
        }

        const style = getComputedStyle(element);

        for (const [property, value] of [['color', style.color], ['background-color', style.backgroundColor], ['border-color', Number.parseFloat(style.borderTopWidth) > 0 ? style.borderTopColor : '']] as const) {
            const channels = rgb(value);

            if (channels !== null && !tokenColour(channels)) {
                note('colour', `${property}: ${value}`, element);
            }
        }

        const size = px(style.fontSize);

        if (size !== null && element.childNodes.length > 0 && Array.from(element.childNodes).some((node) => node.nodeType === Node.TEXT_NODE && (node.textContent ?? '').trim() !== '') && !near(allowed.size, size)) {
            note('type', `font-size: ${style.fontSize}`, element);
        }

        const radius = px(style.borderTopLeftRadius);

        if (radius !== null && !near(allowed.radius, radius)) {
            note('radius', `border-radius: ${style.borderTopLeftRadius}`, element);
        }

        if (style.boxShadow !== 'none' && !allowed.shadow.has(style.boxShadow)) {
            note('elevation', `box-shadow: ${style.boxShadow}`, element);
        }

        for (const [property, value] of [['padding', style.paddingTop], ['padding', style.paddingLeft], ['margin', style.marginTop], ['margin', style.marginLeft], ['gap', style.rowGap], ['gap', style.columnGap]] as const) {
            const amount = px(value);

            if (amount !== null && !near(allowed.spacing, amount)) {
                note('spacing', `${property}: ${value}`, element);
            }
        }

        for (const duration of style.transitionDuration.split(',').map((part) => part.trim())) {
            if (duration !== '0s' && allowed.motion.size > 0 && !allowed.motion.has(duration)) {
                note('motion', `transition-duration: ${duration}`, element);
            }
        }
    }

    const offToken: Tokens['offToken'] = {};

    for (const [group, values] of Object.entries(found)) {
        offToken[group] = Array.from(values.entries()).map(([value, entry]) => ({ value, ...entry })).sort((a, b) => b.count - a.count).slice(0, 25);
    }

    return { offToken };
}

/** The primary actions in each region and the actions a region repeats, rows, menus and lists apart. */
function measureActions(): Actions {
    const region = (element: Element): string => {
        const holder = element.closest('[role="dialog"], [role="alertdialog"], form, section, aside, header, nav, main');

        return holder === null ? 'page' : `${holder.tagName.toLowerCase()}${holder.getAttribute('aria-label') !== null ? `[${holder.getAttribute('aria-label') ?? ''}]` : ''}`;
    };
    const visible = (element: Element): boolean => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden';
    const primaryPerRegion: Record<string, number> = {};

    for (const button of Array.from(document.querySelectorAll('[data-slot="button"]'))) {
        if (visible(button) && /(^|\s)(bg-primary|bg-lime-cta)(\s|$)/.test(button.getAttribute('class') ?? '')) {
            const where = region(button);
            primaryPerRegion[where] = (primaryPerRegion[where] ?? 0) + 1;
        }
    }

    const seen = new Map<string, number>();

    for (const action of Array.from(document.querySelectorAll('button, a[href], [role="button"]'))) {
        if (!visible(action) || action.closest('tr, li, [role="row"], [role="menu"], [role="listbox"], [role="grid"], nav') !== null) {
            continue;
        }

        const name = (action.getAttribute('aria-label') ?? action.textContent).replace(/\s+/g, ' ').trim();

        if (name !== '') {
            const key = `${region(action)}\u0000${action.tagName === 'A' ? 'link' : 'button'}\u0000${name}`;
            seen.set(key, (seen.get(key) ?? 0) + 1);
        }
    }

    const duplicates = Array.from(seen.entries()).filter(([, count]) => count > 1).map(([key, count]) => {
        const [where = '', role = '', name = ''] = key.split('\u0000');

        return { region: where, role, name, count };
    });

    return { primaryPerRegion, duplicates };
}

/** The deferred regions the page settled and whether an empty state is shown. */
function measureStates(): States {
    const regions = Array.from(document.querySelectorAll('[data-region-state]')).map((element) => element.getAttribute('data-region-state') ?? '');

    return { regions: Array.from(new Set(regions)), emptyShown: document.querySelector('[data-slot="empty"] [data-slot="empty-title"]') !== null };
}

/** At the current width: what scrolls the page sideways outside a table frame, and the mandated copy cut under 1.4.12. */
function measureWidth(): Width {
    const width = window.innerWidth;
    const horizontalScroll: string[] = [];

    if (document.documentElement.scrollWidth > width + 1) {
        for (const element of Array.from(document.body.querySelectorAll('*'))) {
            const box = element.getBoundingClientRect();

            if (box.right > width + 1 && box.width > 0 && element.closest('[data-slot="table-frame"], table, [data-slot="scroll-area"]') === null && element.children.length === 0) {
                horizontalScroll.push(`${element.tagName.toLowerCase()}${element.getAttribute('data-slot') !== null ? `[data-slot=${element.getAttribute('data-slot') ?? ''}]` : ''} to ${String(Math.round(box.right))} px`);
            }

            if (horizontalScroll.length >= 10) {
                break;
            }
        }
    }

    // WCAG 2.2 1.4.12's text spacing, applied for the reading and removed after.
    const spacing = document.createElement('style');
    spacing.textContent = '* { line-height: 1.5 !important; letter-spacing: 0.12em !important; word-spacing: 0.16em !important; } p { margin-bottom: 2em !important; }';
    document.head.appendChild(spacing);
    const clippedCopy: string[] = [];

    for (const element of Array.from(document.querySelectorAll('[data-slot="select-value"], label, dd, [aria-readonly="true"], [data-slot="action-bar"] p'))) {
        if (element.getClientRects().length === 0) {
            continue;
        }

        const style = getComputedStyle(element);

        if ((style.overflowX === 'hidden' || style.textOverflow === 'ellipsis' || style.overflowX === 'clip') && element.scrollWidth > element.clientWidth + 1) {
            clippedCopy.push(`${element.tagName.toLowerCase()}: ${element.textContent.replace(/\s+/g, ' ').trim().slice(0, 60)}`);
        }
    }

    spacing.remove();

    return { width, horizontalScroll, clippedCopy: clippedCopy.slice(0, 10) };
}
