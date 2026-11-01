import { expect as base, test } from '@playwright/test';

import { stockRun } from '../stock-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-040; NAV-018–NAV-020; PT-05; PT-06; PT-17): PG-018–PG-020 without a sideways scroll, a table from 768 px, dense rows on the phone.

test.describe.configure({ timeout: 300_000 });

for (const width of [320, 390, 768, 1280]) {
    test(`shows PG-018–PG-020 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await stockRun(page, `stok-lebar-${String(width)}-${String(Date.now())}`);
        const fits = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);
        await page.setViewportSize({ width, height: 844 });

        await page.goto('/gudang/stok');
        await page.getByLabel('Cari produk').fill(run.name);
        await page.getByRole('button', { name: 'Cari', exact: true }).click();
        await expect(page.locator('#konten')).toContainText(run.name);
        expect(await fits()).toBe(true);

        if (width >= 768) {
            await expect(page.getByRole('table', { name: 'Stok' })).toBeVisible();
        } else {
            await expect(page.getByRole('table', { name: 'Stok' })).toHaveCount(0);
        }

        await page.goto(`/gudang/stok/${run.product}`);
        await expect(page.locator('[data-slot="product-lots"]')).toContainText(run.lot);
        expect(await fits()).toBe(true);

        await page.goto(`/gudang/riwayat-stok?produk=${run.product}`);
        await expect(page.locator('#konten')).toContainText(run.name);
        expect(await fits()).toBe(true);
    });
}
