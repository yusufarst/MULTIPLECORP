import { expect as base, type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';
import { type ReservationRun } from './reservation-fixtures';

// TASK-063's page helpers for CJ-17 and CJ-20 (routes/fixtures.php; JourneyCompletionFixture): PG-011 reached with its three deferred
// groups landed — the predicates, the documents with the checklist, the history —, so the step that follows acts on the settled journey;
// PG-019's four quantities read from their own values; and CJ-17's cancellation world, TASK-038's reserved project with its product named.

const JOURNEY = /^\/proyek\/[0-9a-f-]{36}$/;
const GROUPS = ['documents', 'history', 'predicates'];
// A region and a figure wait as the module specs do: a loaded WebKit runner answers PG-011's groups and PG-019 well past the default.
const expect = base.configure({ timeout: 60_000 });

/** PG-011 visited and settled: each of its three deferred groups answered and no region still loading (QB-02; PF-34). */
export async function onProject(page: Page, project: string): Promise<void> {
    const landed = Promise.all(GROUPS.map((group) => page.waitForResponse(async (response) => JOURNEY.test(new URL(response.url()).pathname) && (await response.request().headerValue('x-inertia-partial-data')) === group)));
    await page.goto(`/proyek/${project}`);
    await landed;
    await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0);
}

/** PG-019's quantity block (PT-17) of the product, each figure read from its own value: Stok fisik, Direservasi, Tidak layak pakai, Tersedia. */
export async function expectStock(page: Page, product: string, figures: readonly [string, string, string, string]): Promise<void> {
    await page.goto(`/gudang/stok/${product}`);
    const block = page.locator('[data-slot="inventory-block"] dl');
    await expect(block.locator('dt')).toHaveText(['Stok fisik', 'Direservasi', 'Tidak layak pakai', 'Tersedia']);
    await expect(block.locator('dd data')).toHaveCount(figures.length);

    for (const [index, value] of figures.entries()) {
        await expect(block.locator('dd').nth(index).locator('data')).toHaveAttribute('value', value);
    }
}

export type CancellationRun = ReservationRun & { product: string };

/** The Owner signed in, a company for the run, its confirmed project of ten units with `reserved` of `stock` usable units reserved for it. */
export async function cancellationRun(page: Page, run: string, stock: string, reserved: string): Promise<CancellationRun> {
    await ownerSignedIn(page, run);
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/journey-b3/batal', { data: { company, stock, reserved } });

    if (!response.ok()) {
        throw new Error(`The cancellation fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<CancellationRun, 'company'>) };
}
