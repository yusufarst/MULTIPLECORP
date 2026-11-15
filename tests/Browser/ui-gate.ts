import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
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
type Width = { width: number; horizontalScroll: string[]; clippedCopy: string[]; spacingApplied: boolean };
type Tokens = { offToken: Record<string, { value: string; count: number; sample: string }[]> };
type Actions = { primaryPerRegion: Record<string, number>; duplicates: { region: string; role: string; name: string; count: number }[] };
type States = { regions: string[]; loading: number; empty: { text: boolean; actions: number; images: number }[] };
type Slop = Record<string, { count: number; samples: string[] }>;

let tokenFile: Record<string, string> | null = null;

/** The token file's custom properties by their declared values — the first declaration of each, outside any media query's override. */
function declarations(): Record<string, string> {
    if (tokenFile === null) {
        const found: Record<string, string> = {};

        try {
            const source = readFileSync(join(process.cwd(), 'resources/css/tokens.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');

            for (const [, name = '', value = ''] of source.matchAll(/(--[\w-]+)\s*:\s*([^;{}]+);/g)) {
                if (value.trim() !== 'initial') {
                    found[name] ??= value.trim();
                }
            }
        } catch {
            // Without the file the page's emitted variables stand alone.
        }

        tokenFile = found;
    }

    return tokenFile;
}

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

    const id = /PG-\d{3}/.exec(where)?.[0];

    // The audit's fixture places — the component gallery, the language switch's stand-in — are no page of the 65.
    if (id === undefined) {
        return;
    }

    const size = page.viewportSize();
    const errors: string[] = [];
    // A reading that fails is recorded as such and its items NOT TESTED — never an empty reading that would pass.
    const attempt = async <T>(what: string, run: () => Promise<T>): Promise<T | null> => {
        try {
            return await run();
        } catch (error) {
            errors.push(`${what}: ${error instanceof Error ? (error.message.split('\n')[0] ?? '') : String(error)}`);

            return null;
        }
    };

    const tokens = await attempt('tokens', () => page.evaluate(measureTokens, declarations()));
    const actions = await attempt('actions', () => page.evaluate(measureActions));
    const states = await attempt('states', () => page.evaluate(measureStates));
    const slop = await attempt('slop', () => page.evaluate(measureSlop));
    const widths: Width[] = [];
    const unmeasured: number[] = [];

    try {
        for (const width of BAND_EDGES[engine]) {
            const reading = await attempt(`width ${String(width)}`, async () => {
                await page.setViewportSize({ width, height: 900 });
                await page.evaluate(() => new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done))));

                return page.evaluate(measureWidth);
            });

            if (reading === null) {
                unmeasured.push(width);
            } else {
                widths.push(reading);
            }

            if (engine === 'chromium' && (width === 390 || width === 1280) && !touched.has(`${id} ${String(width)}`)) {
                touched.add(`${id} ${String(width)}`);
                await attempt(`screenshot ${String(width)}`, () => page.screenshot({ path: join(ROOT, 'screens', `${id}-${String(width)}.png`), fullPage: true }));
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
        targets = await attempt('coarse targets', () => coarseTargets(page));
    }

    const untested = (what: string): Item['detail'] => `NOT TESTED — the ${what} reading failed on this visit (errors)`;
    const offToken = tokens === null ? null : Object.values(tokens.offToken).reduce((sum, values) => sum + values.length, 0);
    const spacingOff = tokens === null ? null : (tokens.offToken.spacing ?? []).length;
    const crowded = actions === null ? null : Object.values(actions.primaryPerRegion).filter((count) => count > 1).length;
    const scrolls = widths.filter((reading) => reading.horizontalScroll.length > 0);
    const clipped = widths.filter((reading) => reading.clippedCopy.length > 0);
    const unspaced = widths.filter((reading) => !reading.spacingApplied).map((reading) => reading.width);
    const phone = widths.filter((reading) => reading.width <= 390);
    const small = targets === null ? null : targets.filter((target) => target.height < 44 || target.width < 44);
    const items: Item[] = [
        { item: 'Modern visual quality', result: 'REVIEW', detail: `SLOP-01–SLOP-45 reviewed on the screenshots of this page with its measured marks (slop${slop === null ? ' — NOT TESTED, the reading failed' : ''}) (TASK-065 review record)` },
        offToken === null
            ? { item: 'Design system consistency', result: 'NOT TESTED', detail: untested('token') }
            : { item: 'Design system consistency', result: offToken === 0 ? 'PASS' : 'FAIL', detail: offToken === 0 ? 'every computed colour, type, radius, elevation, motion and spacing value a token' : `${String(offToken)} distinct values outside the tokens (offToken)` },
        crowded === null || actions === null
            ? { item: 'Primary action clear', result: 'NOT TESTED', detail: untested('action') }
            : { item: 'Primary action clear', result: crowded === 0 ? 'PASS' : 'FAIL', detail: crowded === 0 ? 'at most one primary action in each region' : `${String(crowded)} regions with more than one primary action` },
        actions === null
            ? { item: 'Unnecessary duplicate actions', result: 'NOT TESTED', detail: untested('action') }
            : { item: 'Unnecessary duplicate actions', result: actions.duplicates.length === 0 ? 'PASS' : 'FAIL', detail: `${String(actions.duplicates.length)} duplicated actions in a region, outside rows and menus — each to be read against its INT- contract` },
        { item: 'Back action clear', result: 'ELSEWHERE', detail: 'judged by the NAV- paths\' tests (TEST_STRATEGY §8; TL-09)' },
        { item: 'Navigation contract valid', result: 'ELSEWHERE', detail: 'judged by the NAV- paths\' tests (TEST_STRATEGY §8; TL-09)' },
        { item: 'Indonesian default', result: 'ELSEWHERE', detail: 'TASK-067 (TEST_STRATEGY §10), entered by TASK-070' },
        { item: 'Plain Indonesian', result: 'ELSEWHERE', detail: 'TASK-067 (TEST_STRATEGY §10), entered by TASK-070' },
        { item: 'English available', result: 'ELSEWHERE', detail: 'TASK-067 (TEST_STRATEGY §10), entered by TASK-070' },
        { item: 'Missing translations: 0', result: 'ELSEWHERE', detail: 'TASK-067 (TEST_STRATEGY §10), entered by TASK-070' },
        { item: 'Language switch', result: 'ELSEWHERE', detail: 'TASK-067 (TEST_STRATEGY §10), entered by TASK-070' },
        { item: 'Mobile-first', result: scrolls.some((reading) => reading.width <= 390) ? 'FAIL' : phone.length === 0 ? 'NOT TESTED' : 'PASS', detail: `the phone composition at ${phone.map((reading) => String(reading.width)).join(' and ') || 'no phone width'} px without horizontal page scrolling outside a table frame` },
        {
            item: 'Responsive',
            result: scrolls.length > 0 || clipped.length > 0 ? 'FAIL' : unmeasured.length > 0 || unspaced.length > 0 ? 'NOT TESTED' : 'PASS',
            detail: `${widths.map((reading) => String(reading.width)).join(', ')} px measured on ${engine}${engine === 'webkit' ? ' — the other band edges NOT TESTED (APPR-038)' : ''}${unmeasured.length > 0 ? `; ${unmeasured.join(', ')} px not measured (errors)` : ''}${unspaced.length > 0 ? `; 1.4.12's spacing not applied at ${unspaced.join(', ')} px, the copy unjudged there` : ''}; ${String(scrolls.length)} widths with horizontal scrolling, ${String(clipped.length)} with mandated copy cut under 1.4.12's text spacing`,
        },
        { item: 'Adaptive', result: small === null ? 'NOT TESTED' : small.length === 0 ? 'PASS' : 'FAIL', detail: small === null ? (engine === 'webkit' ? 'the coarse pointer measured on Chromium' : untested('coarse pointer')) : `${String(small.length)} controls below 44 px under a coarse pointer at 390 px` },
        states === null
            ? { item: 'Loading state', result: 'NOT TESTED', detail: untested('state') }
            : { item: 'Loading state', result: states.loading > 0 ? 'FAIL' : 'PASS', detail: states.loading > 0 ? `${String(states.loading)} regions still loading when the visit had settled` : 'no region left loading once the visit settled; each region\'s own spinner is its module\'s TL-08/TL-09 test' },
        states === null
            ? { item: 'Empty state', result: 'NOT TESTED', detail: untested('state') }
            : states.empty.length === 0
              ? { item: 'Empty state', result: 'NOT TESTED', detail: 'not reached on this visit\'s data' }
              : { item: 'Empty state', result: states.empty.every((one) => one.actions <= 1 && one.images === 0 && one.text) ? 'PASS' : 'FAIL', detail: `${String(states.empty.length)} empty states: one sentence, at most one action and no illustration each (PT-19; SLOP-13) — ${String(states.empty.filter((one) => one.actions > 1 || one.images > 0 || !one.text).length)} beyond` },
        { item: 'Error state', result: 'NOT TESTED', detail: 'an outcome\'s error is judged by the INT- tests (TL-08), not on this visit' },
        { item: 'Destructive action safety', result: 'ELSEWHERE', detail: 'IP-08\'s confirmation judged with each INT- contract (TL-08)' },
        { item: 'Accessibility critical checks', result: 'ELSEWHERE', detail: 'TASK-066 (TEST_STRATEGY §11), entered by TASK-070' },
        spacingOff === null
            ? { item: 'Visual breathing room', result: 'NOT TESTED', detail: untested('token') }
            : { item: 'Visual breathing room', result: spacingOff === 0 ? 'PASS' : 'FAIL', detail: spacingOff === 0 ? 'every measured spacing a token' : `${String(spacingOff)} spacing values outside the tokens` },
        { item: 'Content density appropriate', result: 'REVIEW', detail: 'reviewed on the screenshots against OC-09 and AICWDF §18.2 (TASK-065 review record)' },
    ];
    const file = join(ROOT, engine, `${where.replace(/[^A-Za-z0-9-]+/g, '-')}.json`);
    mkdirSync(dirname(file), { recursive: true });
    writeFileSync(file, `${JSON.stringify({ where, page: id, engine, url: page.url(), items, tokens, actions, states, slop, widths, unmeasured, targets, errors }, null, 2)}\n`);
}

/** The controls under a coarse pointer at 390 px, in a touch context of the same session. */
async function coarseTargets(page: Page): Promise<{ name: string; width: number; height: number }[]> {
    const browser = page.context().browser();

    if (browser === null) {
        throw new Error('no browser to open a touch context in');
    }

    const context = await browser.newContext({ storageState: await page.context().storageState(), hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 }, ignoreHTTPSErrors: true });

    try {
        const touch = await context.newPage();
        await touch.goto(page.url(), { waitUntil: 'load' });

        return await touch.evaluate(() => Array.from(document.querySelectorAll<HTMLElement>('button, a[href], input:not([type="hidden"]), select, textarea, [role="button"], [role="tab"], [role="checkbox"], [role="switch"]'))
            // A visually hidden control — the skip link until it has focus — is the keyboard's, not a touch target.
            .filter((element) => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden' && !element.closest('p, li > a:only-child')
                && getComputedStyle(element).clipPath !== 'inset(50%)' && getComputedStyle(element).getPropertyValue('clip') !== 'rect(0px, 0px, 0px, 0px)')
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
function measureTokens(declared: Record<string, string>): Tokens {
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

    // The probe sits in a holder with sentinel values, so a property that is no value of the kind asked for — invalid at
    // computed-value time, so inherited — reads as the sentinel and is left out. Every custom property of the token file
    // is a candidate: `@theme inline`'s keys are not emitted, their utilities naming the brand set directly (tokens.css).
    const holder = document.createElement('div');
    holder.style.setProperty('color', 'rgb(1, 2, 3)');
    holder.style.setProperty('font-size', '1.234px');
    const probe = document.createElement('div');
    holder.appendChild(probe);
    document.body.appendChild(holder);
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
    // A token by its custom property where the page emits it, otherwise by the value the token file declares — Tailwind
    // inlines a `@theme` value into its utilities (a shadow, a spacing) without emitting the variable.
    const values = new Map<string, string>(Array.from(names).map((name) => [name, `var(${name})`]));

    for (const [name, value] of Object.entries(declared)) {
        if (!values.has(name)) {
            values.set(name, value);
        }
    }

    const resolved = (property: string, name: string): string => {
        probe.style.setProperty(property, values.get(name) ?? `var(${name})`);
        const value = getComputedStyle(probe).getPropertyValue(property);
        probe.style.removeProperty(property);

        return value;
    };
    const tokens = Array.from(values.keys());
    const px = (value: string): number | null => (/^-?[\d.]+px$/.test(value) ? Math.abs(Number.parseFloat(value)) : null);
    // A shadow's visible layers: Tailwind composes the ring and inset placeholders as transparent layers, which paint nothing.
    const layers = (value: string): string[] => (value === 'none' || value === '' ? [] : value.split(/,(?![^(]*\))/).map((part) => part.trim()).filter((part) => !/^rgba\(\d+, \d+, \d+, 0\)/.test(part)));
    const allowed = {
        colour: tokens.map((name) => resolved('color', name)).filter((value) => value !== 'rgb(1, 2, 3)').map(rgb).filter((value): value is [number, number, number, number] => value !== null),
        size: new Set(tokens.filter((name) => /^--(text|m)-/.test(name) && !/line-height|letter-spacing|weight|tracking/.test(name)).map((name) => px(resolved('font-size', name))).filter((value): value is number => value !== null && value !== 1.234)),
        weight: new Set(tokens.filter((name) => name.startsWith('--font-weight-') || /weight/.test(name)).map((name) => resolved('font-weight', name)).filter((value) => value !== '')),
        radius: new Set(tokens.filter((name) => name.startsWith('--radius')).map((name) => px(resolved('border-top-left-radius', name))).filter((value): value is number => value !== null)),
        shadow: new Set(tokens.map((name) => layers(resolved('box-shadow', name)).join(', ')).filter((value) => value !== '')),
        // Every length of the token file, the phone's `--m-` set included: a card's `p-m-card` is `--m-card-pad`.
        spacing: new Set(tokens.filter((name) => !/radius|text|font|weight|leading|tracking|line-height|letter-spacing|dur|ease|z-|shadow|color|brand/.test(name)).map((name) => px(resolved('padding-top', name))).filter((value): value is number => value !== null)),
        motion: new Set(tokens.filter((name) => /dur/.test(name)).map((name) => resolved('transition-duration', name)).filter((value) => value !== '')),
    };
    holder.remove();

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
        // The document's paper is content in its own millimetre scale (paper.css; DESIGN_SYSTEM §14.3), not the interface's
        // tokens; a visually hidden element paints nothing until it has focus.
        if (element.getClientRects().length === 0 || element.closest('svg, .paper') !== null || element === holder || element === probe) {
            continue;
        }

        const style = getComputedStyle(element);

        if (style.clipPath === 'inset(50%)' || style.getPropertyValue('clip') === 'rect(0px, 0px, 0px, 0px)') {
            continue;
        }

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

        // A ring — a zero-offset spread, focus or selection — is judged by its colour; the elevation by the rest.
        const visibleLayers = layers(style.boxShadow);
        const rings = visibleLayers.filter((layer) => /\s0px 0px 0px [\d.]+px( inset)?$/.test(layer));

        for (const ring of rings) {
            const channels = rgb(/^(rgba?\([^)]*\))/.exec(ring)?.[1] ?? '');

            if (channels !== null && !tokenColour(channels)) {
                note('colour', `ring: ${ring}`, element);
            }
        }

        const elevation = visibleLayers.filter((layer) => !rings.includes(layer)).join(', ');

        if (elevation !== '' && !allowed.shadow.has(elevation)) {
            note('elevation', `box-shadow: ${elevation}`, element);
        }

        // Padding and gap carry the spacing scale; a margin is most often `auto`, which computes to a length of its own.
        for (const [property, value] of [['padding', style.paddingTop], ['padding', style.paddingLeft], ['gap', style.rowGap], ['gap', style.columnGap]] as const) {
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
        // A field's own control — a password's "Tampilkan" — belongs to its field, never a duplicate of another field's.
        if (!visible(action) || action.closest('tr, li, [role="row"], [role="menu"], [role="listbox"], [role="grid"], nav, [data-slot="input-group"]') !== null) {
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
    const shown = (element: Element): boolean => element.getClientRects().length > 0;
    const regions = Array.from(document.querySelectorAll('[data-region-state]')).filter(shown).map((element) => element.getAttribute('data-region-state') ?? '');
    // PT-19's empty state — a region's or a list's —: its sentence, its actions and any picture larger than an icon.
    const empty = Array.from(document.querySelectorAll('[data-slot="empty"], [data-region-state="empty"], [data-region-state="no-match"]'))
        .filter((element) => shown(element) && (element.parentElement?.closest('[data-slot="empty"], [data-region-state="empty"], [data-region-state="no-match"]') ?? null) === null)
        .map((element) => ({
            text: element.textContent.trim() !== '',
            actions: Array.from(element.querySelectorAll('button, a[href]')).filter(shown).length,
            images: Array.from(element.querySelectorAll('img, svg')).filter((picture) => picture.getBoundingClientRect().width > 24).length,
        }));

    return { regions: Array.from(new Set(regions)), loading: regions.filter((state) => state === 'loading').length, empty };
}

/**
 * The anti-slop audit's measurable marks (DESIGN_SYSTEM §18), each with its count and a few samples for the reviewer, who
 * judges them against the rule and GA1 d)'s dispositions — a pill on a state is allowed, a pill on a card is not.
 */
function measureSlop(): Slop {
    const marks: Slop = {};
    const describe = (element: Element, extra = ''): string => `${element.tagName.toLowerCase()}${element.getAttribute('data-slot') !== null ? `[data-slot=${element.getAttribute('data-slot') ?? ''}]` : ''}${extra === '' ? '' : ` ${extra}`} «${element.textContent.replace(/\s+/g, ' ').trim().slice(0, 40)}»`;
    const mark = (slop: string, element: Element, extra = ''): void => {
        const entry = (marks[slop] ??= { count: 0, samples: [] });
        entry.count += 1;

        if (entry.samples.length < 6) {
            entry.samples.push(describe(element, extra));
        }
    };
    const emoji = /\p{Extended_Pictographic}/u;
    // The document's paper and a chart are content, not the interface's decoration.
    const content = '.paper, [data-slot="chart"], [data-slot="paper-frame"]';
    // SLOP-26 as GA1 d) revised it: a title at the reference's `--text-title` and no larger — 20 px if it cannot be read.
    const sizer = document.createElement('span');
    sizer.style.setProperty('font-size', 'var(--text-title)');
    document.body.appendChild(sizer);
    const read = Number.parseFloat(getComputedStyle(sizer).fontSize);
    sizer.remove();
    const title = Number.isFinite(read) && read > 20 ? read : 20;

    for (const element of Array.from(document.body.querySelectorAll('*'))) {
        if (element.getClientRects().length === 0 || element.closest(content) !== null) {
            continue;
        }

        const style = getComputedStyle(element);
        const box = element.getBoundingClientRect();

        // A visually hidden element — the skip link until it has focus — paints nothing.
        if (style.clipPath === 'inset(50%)' || style.getPropertyValue('clip') === 'rect(0px, 0px, 0px, 0px)') {
            continue;
        }

        if (style.backgroundImage.includes('gradient')) mark('SLOP-06/07 gradient', element);
        if ((style.backdropFilter !== '' && style.backdropFilter !== 'none') || style.filter.includes('blur')) mark('SLOP-10 blur', element);
        if (style.animationName !== 'none' && style.animationDuration !== '0s') mark('SLOP-15/43 animation', element, `${style.animationName} ${style.animationDuration} ${style.animationIterationCount}`);
        if (/^H[1-6]$/.test(element.tagName) || element.getAttribute('role') === 'heading') {
            if (Number.parseFloat(style.fontSize) > title + 0.5) mark('SLOP-26 heading above the title size', element, `${style.fontSize} > ${String(title)}px`);
        }
        if (box.height > 0 && box.width > box.height * 1.5 && Number.parseFloat(style.borderTopLeftRadius) >= box.height / 2 - 0.5 && Number.parseFloat(style.borderTopLeftRadius) > 4) mark('SLOP-05 pill', element);
        if ((element.tagName === 'IMG' || (element.tagName === 'svg' && element.parentElement?.closest('svg') === null)) && box.width > 64 && box.height > 64) mark('SLOP-13 image above 64 px', element, `${String(Math.round(box.width))}x${String(Math.round(box.height))}`);
        if (Array.from(element.childNodes).some((node) => node.nodeType === Node.TEXT_NODE && emoji.test(node.textContent ?? ''))) mark('SLOP-22 emoji', element);
        if (element.matches('[data-slot="avatar"], [class*="avatar"]')) mark('SLOP-45 avatar', element);
        if (element.matches('[role="progressbar"], progress')) mark('SLOP-44 progress', element);
    }

    const buttons = Array.from(document.querySelectorAll('button, a[data-slot="button"]')).filter((button) => button.getClientRects().length > 0 && button.closest(content) === null && button.textContent.trim() !== '');
    const iconed = buttons.filter((button) => button.querySelector('svg') !== null);
    marks['SLOP-24 labelled buttons with an icon'] = { count: iconed.length, samples: [`of ${String(buttons.length)} labelled buttons`, ...iconed.slice(0, 5).map((button) => describe(button))] };

    for (const button of buttons) {
        if (/^(suka|like|reaksi|react)/i.test(button.textContent.trim())) mark('SLOP-40/41 reaction', button);
        if (/^muat lagi$/i.test(button.textContent.trim())) mark('SLOP-39 explicit "Muat Lagi"', button);
    }

    return marks;
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

    // WCAG 2.2 1.4.12's text spacing, applied for the reading and removed after. The page's CSP refuses an inline
    // stylesheet without its nonce, so the rules go in as a constructed sheet — no inline style — or, where that is not
    // supported, a style element carrying the page's own nonce; the reading counts only once the spacing has taken effect.
    const rules = '* { line-height: 1.5 !important; letter-spacing: 0.12em !important; word-spacing: 0.16em !important; } p { margin-bottom: 2em !important; }';
    const applied = (): boolean => getComputedStyle(document.body).wordSpacing !== '0px' && getComputedStyle(document.body).letterSpacing !== 'normal';
    const sheet = new CSSStyleSheet();
    sheet.replaceSync(rules);
    document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet];
    let fallback: HTMLStyleElement | null = null;

    if (!applied()) {
        fallback = document.createElement('style');
        fallback.nonce = document.querySelector<HTMLElement>('script[nonce], style[nonce]')?.nonce ?? '';
        fallback.textContent = rules;
        document.head.appendChild(fallback);
    }

    const spacingApplied = applied();
    const undo = (): void => {
        document.adoptedStyleSheets = document.adoptedStyleSheets.filter((adopted) => adopted !== sheet);
        fallback?.remove();
    };
    const clippedCopy: string[] = [];

    for (const element of spacingApplied ? Array.from(document.querySelectorAll('[data-slot="select-value"], label, dd, [aria-readonly="true"], [data-slot="action-bar"] p')) : []) {
        if (element.getClientRects().length === 0) {
            continue;
        }

        const style = getComputedStyle(element);

        if ((style.overflowX === 'hidden' || style.textOverflow === 'ellipsis' || style.overflowX === 'clip') && element.scrollWidth > element.clientWidth + 1) {
            clippedCopy.push(`${element.tagName.toLowerCase()}: ${element.textContent.replace(/\s+/g, ' ').trim().slice(0, 60)}`);
        }
    }

    undo();

    return { width, horizontalScroll, clippedCopy: clippedCopy.slice(0, 10), spacingApplied };
}
