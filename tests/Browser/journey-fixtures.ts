import { writeFile } from 'node:fs/promises';
import { expect, type Page, type TestInfo } from '@playwright/test';

// TASK-062's shared journey checks (TEST_STRATEGY §7, §8, §16; AICWDF §25; TS-11): every critical journey watches its
// page's console and network from its first step — a critical console error or a critical failed request fails it —,
// keeps both captures as attachments of its evidence, and ends with ReconcileGuards reporting no discrepancy at all for
// its records: every guard family reconciled, each difference traced to the company owning its record, none allowed for
// the journey's own companies — created for its run, so no other spec of the shared browser database writes them —, and
// a difference tracing to no company counted as the journey's (fail-closed).

type Captured = { console: string[]; failed: string[]; csp: string[]; requests: number; engine: string; pending: Promise<void>[] };

/**
 * WebKit's own report on the internal styling of every native `<select>` (TASK-015's components-csp.spec.ts proves it comes
 * from the engine with no library on the page): set aside on WebKit alone, exactly as that spec does. Every other CSP
 * violation fails the journey.
 */
const ENGINE_SELECT = 'style-src-attr SELECT inline';

/** Requests a journey may see fail by design: none — a step that expects a refusal answers 422 or 409, never these. */
const CRITICAL = new Set([404, 405, 419, 500, 502, 503, 504]);

/**
 * ROUTE-227's thumbnail answers 404 while JB-04 has not made it yet — `FileDownloads::derivative` owes it then and
 * `FileThumbnail` shows its neutral placeholder (PT-22) —, as it does at once after an upload: an expected answer, never
 * a critical failure. Only that GET, path and status; every other 404 fails the journey, and the denial suites are TASK-069's.
 */
function owedThumbnail(method: string, path: string, status: number): boolean {
    return method === 'GET' && status === 404 && /^\/berkas\/[0-9a-f-]{36}\/gambar-kecil$/.test(path);
}

export function watch(page: Page): Captured {
    const captured: Captured = { console: [], failed: [], csp: [], requests: 0, engine: page.context().browser()?.browserType().name() ?? '', pending: [] };

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

        if (CRITICAL.has(response.status()) && !path.startsWith('/__fixtures') && !owedThumbnail(response.request().method(), path, response.status())) {
            captured.failed.push(`${response.request().method()} ${path} ${String(response.status())}`);
        }
    });
    page.on('requestfailed', (request) => {
        const reason = request.failure()?.errorText ?? '';

        // A request the browser cancels because the page moved on is no failure (WebKit and Chromium name it so).
        if (/cancel|abort/i.test(reason)) return;

        const failed = `${request.method()} ${new URL(request.url()).pathname} ${reason}`;

        // Headless WebKit shows no PDF inline: it interrupts the frame of an inline rendition the server answered in full
        // (run 43216792494). Set aside on WebKit alone, for that request alone — GET of ROUTE-226's inline rendition
        // (`/berkas/{uuid}/tampil`) answered 200, `application/pdf`, `inline` —, decided before clean() reads the capture;
        // the journey asserts the rendition's own response. Any other interrupted frame stays a failure.
        if (captured.engine === 'webkit' && reason === 'Frame load interrupted' && request.method() === 'GET' && /^\/berkas\/[0-9a-f-]{36}\/tampil$/.test(new URL(request.url()).pathname)) {
            captured.pending.push(request.response().then(async (response) => {
                const headers = response === null ? {} : await response.allHeaders();
                const pdf = response !== null && response.status() === 200 && (headers['content-type'] ?? '').startsWith('application/pdf') && (headers['content-disposition'] ?? '').startsWith('inline');

                if (!pdf) captured.failed.push(failed);
            }));

            return;
        }

        captured.failed.push(failed);
    });

    return captured;
}

/**
 * A capture written into the test's own output directory and attached by path, so that a passing journey's evidence stays in
 * the run's browser artifact as a failing one's does (TEST_STRATEGY §16) — a body attachment alone is not kept on a pass.
 */
async function keep(testInfo: TestInfo, name: string, value: unknown): Promise<void> {
    const path = testInfo.outputPath(name);
    await writeFile(path, JSON.stringify(value, null, 2));
    await testInfo.attach(name, { path, contentType: 'application/json' });
}

/** The journey's evidence (TEST_STRATEGY §16): the captures kept, then no critical console error or failed request. */
export async function clean(captured: Captured, testInfo: TestInfo): Promise<void> {
    // Every deferred decision on a captured request is made before the capture is read.
    await Promise.all(captured.pending);
    await keep(testInfo, 'console-and-network.json', captured);
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
    await keep(testInfo, 'reconcile-guards.json', answer);
    expect(answer.families, 'every guard family reconciled').toBeGreaterThan(0);
    expect(answer.differences, 'ReconcileGuards reports no discrepancy after the journey (TS-11)').toEqual([]);
}
