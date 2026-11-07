import { expect, type Page } from '@playwright/test';

import { numberingCompany } from './numbering-fixtures';
import { purchaseRun, type PurchaseRun } from './purchase-fixtures';

// TASK-040's browser fixture (routes/fixtures.php; StockFixture) over TASK-037's: ON HAND 10, RESERVED 3, UNUSABLE 2 at a rack, a condition change to reverse, another company's lot of the same product.

export type StockRun = PurchaseRun & { other: string; product: string; name: string; sku: string; rack: string; movement: string; lot: string; other_code: string; other_name: string };

export async function stockRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<StockRun> {
    const purchased = await purchaseRun(page, run, locale);
    const other = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/stock', { data: { company: purchased.company, other, project: purchased.project } });

    if (!response.ok()) {
        throw new Error(`The stock fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { ...purchased, other, ...((await response.json()) as Pick<StockRun, 'product' | 'name' | 'sku' | 'rack' | 'movement' | 'lot' | 'other_code' | 'other_name'>) };
}

/**
 * PG-018's search by product name. The page loads its deferred value exposure after the first render; a term typed
 * while that reload lands was lost once on WebKit (PR #71: the field was empty right after the fill and "Cari" sent
 * `cari=`), so the search waits for the deferred region to settle and checks the field holds the term before it submits.
 */
export async function searchStock(page: Page, name: string): Promise<void> {
    const form = page.locator('form[role="search"]');
    await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0, { timeout: 60_000 });
    await form.getByLabel('Cari produk').fill(name);
    await expect(form.getByLabel('Cari produk')).toHaveValue(name);
    await form.getByRole('button', { name: 'Cari', exact: true }).click();
}
