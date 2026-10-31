import { expect, type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-037's browser fixture (routes/fixtures.php; PurchaseFixture): the Owner signed in, a company with its PURCHASE and PO numbering started, two suppliers, goods and a service, a bill, a confirmed warehouse project with its shortage.

export type PurchaseRun = { purchase: string | null; company: string; code: string; project: string; project_number: string; supplier: string; other_supplier: string; goods: string; service: string; base_unit: string; tax: string; bill: string };

export async function purchaseRun(page: Page, run: string, locale: 'id' | 'en' = 'id', purchased = false): Promise<PurchaseRun> {
    await ownerSignedIn(page, run, locale);
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/purchase', { data: { company, purchased } });

    if (!response.ok()) {
        throw new Error(`The purchase fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<PurchaseRun, 'company'>) };
}

/** Chooses the supplier by its lookup. */
export async function chooseSupplier(page: Page, name: string): Promise<void> {
    await page.getByRole('combobox', { name: 'Pemasok' }).fill(name);
    await page.getByRole('option', { name: new RegExp(name) }).first().click();
}

/** Fills the trailing line: the product by its lookup, its quantity and price. */
export async function addPurchaseLine(page: Page, index: number, product: string, quantity: string, price: string): Promise<void> {
    const row = page.locator('[data-slot="line-editor"] [data-line]').nth(index);
    await row.getByRole('combobox', { name: 'Produk/Jasa' }).fill(product);
    await page.getByRole('option', { name: new RegExp(product) }).first().click();
    await expect(row.getByRole('combobox', { name: 'Satuan' })).toBeEnabled();
    await row.getByRole('textbox', { name: 'Jumlah' }).fill(quantity);
    await row.getByRole('textbox', { name: 'Harga' }).fill(price);
}
