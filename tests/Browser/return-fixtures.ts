import { type Page } from '@playwright/test';

import { adjustmentRun, type AdjustmentRun } from './adjustment-fixtures';

// TASK-044's browser fixture over TASK-042's: the signed-in account records supplier returns and closes cases in the run's company; the purchase of the run's lot and one unit's current net cost.

export type ReturnRun = AdjustmentRun & { purchase: string; unit_cost: string };

export async function returnRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<ReturnRun> {
    const adjusted = await adjustmentRun(page, run, locale);
    const response = await page.request.post('/__fixtures/returns', { data: { company: adjusted.company, lot: adjusted.lot } });

    if (!response.ok()) {
        throw new Error(`The return fixture failed: ${String(response.status())}`);
    }

    return { ...adjusted, ...((await response.json()) as Pick<ReturnRun, 'purchase' | 'unit_cost'>) };
}

/** PG-028's deferred proofs land after each visit of a case: the journey types once they have, as WebKit lost values filled while deferred props landed. */
export function caseSettled(page: Page): Promise<unknown> {
    return page.waitForResponse(async (response) => response.url().includes('/gudang/retur-pemasok') && (await response.request().headerValue('x-inertia-partial-data')) !== null);
}

/** The form a purchase opens, its lot chosen: the units of the lot listed. */
export async function openReturn(page: Page, run: ReturnRun): Promise<void> {
    await page.goto(`/gudang/retur-pemasok?pembelian=${run.purchase}`);
    const lot = page.locator('[data-slot="return-form"]').getByLabel('Lot yang diretur');
    await lot.selectOption({ value: (await lot.locator('option', { hasText: run.lot }).getAttribute('value')) ?? '' });
    await page.waitForURL(/lot=/);
    await page.locator('[data-slot="return-place"]').first().waitFor();
}
