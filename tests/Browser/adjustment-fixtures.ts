import { type Page } from '@playwright/test';

import { stockRun, type StockRun } from './stock-fixtures';

// TASK-042's browser fixture over TASK-040's: ON HAND 14 in the pool — 10 of the company's own, 2 of them unusable, 3 reserved —, 4 of another company's; a second rack; a pool proof to cite.

export type AdjustmentRun = StockRun & { target: string; proof: string };

/** PG-027's deferred details land after each visit of it: a journey types once they have, as WebKit lost values filled while they landed. */
export async function settledVisit(page: Page, navigate: () => Promise<unknown>): Promise<void> {
    const landed = page.waitForResponse(async (response) => response.url().includes('/gudang/penyesuaian') && (await response.request().headerValue('x-inertia-partial-data')) !== null);
    await navigate();
    await landed;
}

export async function adjustmentRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<AdjustmentRun> {
    const stocked = await stockRun(page, run, locale);
    const response = await page.request.post('/__fixtures/adjustments', { data: {} });

    if (!response.ok()) {
        throw new Error(`The adjustment fixture failed: ${String(response.status())}`);
    }

    return { ...stocked, ...((await response.json()) as Pick<AdjustmentRun, 'target' | 'proof'>) };
}
