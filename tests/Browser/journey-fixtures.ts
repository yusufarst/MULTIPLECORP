import { expect, type Page, type TestInfo } from '@playwright/test';

// TASK-062's shared journey checks (TEST_STRATEGY §7, §8, §16; AICWDF §25; TS-11): every critical journey watches its
// page's console and network from its first step — a critical console error or a critical failed request fails it —,
// keeps both captures as attachments of its evidence, and ends with ReconcileGuards reporting no discrepancy at all for
// its records: every guard family reconciled, each difference traced to the company owning its record, none allowed for
// the journey's own companies — created for its run, so no other spec of the shared browser database writes them —, and
// a difference tracing to no company counted as the journey's (fail-closed).

type Captured = { console: string[]; failed: string[]; csp: string[]; requests: number; engine: string };

/**
 * WebKit's own report on the internal styling of every native `<select>` (TASK-015's components-csp.spec.ts proves it comes
 * from the engine with no library on the page): set aside on WebKit alone, exactly as that spec does. Every other CSP
 * violation fails the journey.
 */
const ENGINE_SELECT = 'style-src-attr SELECT inline';

/** Requests a journey may see fail by design: none — a step that expects a refusal answers 422 or 409, never these. */
const CRITICAL = new Set([404, 405, 419, 500, 502, 503, 504]);

export function watch(page: Page): Captured {
    const captured: Captured = { console: [], failed: [], csp: [], requests: 0, engine: page.context().browser()?.browserType().name() ?? '' };

    // Every CSP violation of every page the journey loads, as components-csp.spec.ts records them (WS-08).
    void page.exposeFunction('__journeyCsp', (violation: string) => {
        captured.csp.push(violation);
    });
    void page.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (event) => {
            const report = Reflect.get(window, '__journeyCsp') as ((violation: string) => void) | undefined;
            report?.(`${event.violatedDirective} ${(event.target as Element | null)?.tagName ?? ''} ${event.blockedURI}`.trim());
        });
    });

    page.on('console', (message) => {
        // A response's status is judged once, by the network capture below; the browser's own line about it is not a second error.
        // A CSP refusal is judged once, by its securitypolicyviolation record above; the console's line about it is not a second error.
        if (message.type() === 'error' && !message.text().startsWith('Failed to load resource') && !message.text().startsWith('Refused to apply')) {
            captured.console.push(message.text().slice(0, 300));
        }
    });
    page.on('pageerror', (error) => {
        // Inertia raises a network error for a request the browser cancelled as the page moved on (WebKit names it so);
        // a request that truly failed is caught below by requestfailed, which leaves out only the cancellations.
        if (error.message.startsWith('HttpNetworkError')) return;
        captured.console.push(`pageerror: ${error.message.slice(0, 300)}`);
    });
    page.on('response', (response) => {
        captured.requests++;
        const path = new URL(response.url()).pathname;

        if (CRITICAL.has(response.status()) && !path.startsWith('/__fixtures')) {
            captured.failed.push(`${response.request().method()} ${path} ${String(response.status())}`);
        }
    });
    page.on('requestfailed', (request) => {
        const reason = request.failure()?.errorText ?? '';

        // A request the browser cancels because the page moved on is no failure (WebKit and Chromium name it so).
        if (!/cancel|abort/i.test(reason)) {
            captured.failed.push(`${request.method()} ${new URL(request.url()).pathname} ${reason}`);
        }
    });

    return captured;
}

/** The journey's evidence (TEST_STRATEGY §16): the captures attached, then no critical console error or failed request. */
export async function clean(captured: Captured, testInfo: TestInfo): Promise<void> {
    await testInfo.attach('console-and-network.json', { body: JSON.stringify(captured, null, 2), contentType: 'application/json' });
    expect(captured.console, 'no critical console error (TEST_STRATEGY §8)').toEqual([]);
    expect(captured.failed, 'no critical failed request (TEST_STRATEGY §8)').toEqual([]);
    expect(captured.csp.filter((violation) => !(captured.engine === 'webkit' && violation === ENGINE_SELECT)), 'no CSP violation (WS-08)').toEqual([]);
}

/**
 * TS-11: ReconcileGuards over every family reports no discrepancy for the journey's records — the companies it ran on,
 * named by their public identifiers. The whole database's count is attached as evidence beside it.
 */
export async function guardsClean(page: Page, testInfo: TestInfo, companies: string[]): Promise<void> {
    expect(companies.length, 'a journey names the companies it ran on').toBeGreaterThan(0);
    const response = await page.request.get(`/__fixtures/rekonsiliasi?perusahaan=${companies.map(encodeURIComponent).join(',')}`);

    if (!response.ok()) {
        throw new Error(`The reconciliation fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    const answer = (await response.json()) as { differences: string[]; all: number; families: number };
    await testInfo.attach('reconcile-guards.json', { body: JSON.stringify(answer, null, 2), contentType: 'application/json' });
    expect(answer.families, 'every guard family reconciled').toBeGreaterThan(0);
    expect(answer.differences, 'ReconcileGuards reports no discrepancy after the journey (TS-11)').toEqual([]);
}
