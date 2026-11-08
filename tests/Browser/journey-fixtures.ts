import { expect, type Page, type TestInfo } from '@playwright/test';

// TASK-062's shared journey checks (TEST_STRATEGY §7, §8, §16; AICWDF §25; TS-11): every critical journey watches its
// page's console and network from its first step — a critical console error or a critical failed request fails it —,
// keeps both captures as attachments of its evidence, and compares ReconcileGuards' differences before and after it,
// clean when it adds none (the browser database is shared by every spec of a run).

type Captured = { console: string[]; failed: string[]; requests: number };

/** Requests a journey may see fail by design: none — a step that expects a refusal answers 422 or 409, never these. */
const CRITICAL = new Set([404, 405, 419, 500, 502, 503, 504]);

export function watch(page: Page): Captured {
    const captured: Captured = { console: [], failed: [], requests: 0 };

    page.on('console', (message) => {
        if (message.type() === 'error') {
            captured.console.push(message.text().slice(0, 300));
        }
    });
    page.on('pageerror', (error) => {
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
}

export async function guardDifferences(page: Page): Promise<string[]> {
    const response = await page.request.get('/__fixtures/rekonsiliasi');

    if (!response.ok()) {
        throw new Error(`The reconciliation fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return ((await response.json()) as { differences: string[] }).differences;
}

/** TS-11: the journey added no difference between a guard and its facts. */
export async function guardsClean(page: Page, before: string[]): Promise<void> {
    const after = await guardDifferences(page);
    expect(after.filter((difference) => !before.includes(difference)), 'ReconcileGuards clean after the journey (TS-11)').toEqual([]);
}
