import { expect as base, test } from '@playwright/test';

import { stockRun } from '../stock-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-040; LOCALIZATION §3; §6.6; DOD-16): PG-018–PG-020 in both languages, keys of the catalogue and never hardcoded copy.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { stock: 'Stok', detail: 'Stok fisik', history: 'Riwayat Stok', places: 'Per Rak', products: 'Per Produk', section: 'Lot', reverse: 'Balikkan', title: 'Balikkan pergerakan ini?' }],
    ['en', { stock: 'Stock', detail: 'Physical stock', history: 'Stock history', places: 'By Rack', products: 'By Product', section: 'Lots', reverse: 'Reverse', title: 'Reverse this movement?' }],
] as const) {
    test(`shows PG-018–PG-020 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await stockRun(page, `stok-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/gudang/stok?cari=${encodeURIComponent(run.name)}`);
        await expect(page.getByRole('heading', { name: texts.stock, exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.places })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.products })).toBeVisible();

        await page.goto(`/gudang/stok/${run.product}`);
        await expect(page.locator('[data-slot="inventory-block"]')).toContainText(texts.detail);
        await expect(page.getByRole('heading', { name: texts.section, exact: true })).toBeVisible();

        await page.goto(`/gudang/riwayat-stok?produk=${run.product}&jenis=CONDITION_CHANGE`);
        await expect(page.getByRole('heading', { name: texts.history, exact: true })).toBeVisible();
        await page.getByRole('button', { name: texts.reverse, exact: true }).first().click();
        await expect(page.getByRole('alertdialog', { name: texts.title })).toBeVisible();
    });
}
