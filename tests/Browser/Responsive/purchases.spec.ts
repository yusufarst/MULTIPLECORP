import { expect as base, test } from '@playwright/test';

import { addPurchaseLine, chooseSupplier, purchaseRun } from '../purchase-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-037; NV-06; NAV-015–NAV-017; PT-05; PT-06; PT-10): PG-015–PG-017 without a sideways scroll, a table from 768 px, dense rows on the phone; `[desk]`, readable on the phone with small edits.

test.describe.configure({ timeout: 300_000 });

for (const width of [320, 390, 768, 1280]) {
    test(`shows PG-015–PG-017 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await purchaseRun(page, `beli-lebar-${String(width)}-${String(Date.now())}`);
        const fits = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

        await page.goto(`/pembelian/baru?perusahaan=${run.company}`);
        await chooseSupplier(page, run.supplier);
        await addPurchaseLine(page, 0, run.goods, '2', '1000');
        await page.getByRole('button', { name: 'Simpan Pembelian' }).click();
        await page.waitForURL(/\/pembelian\/[0-9a-f-]{36}$/);
        const detail = page.url();

        await page.setViewportSize({ width, height: 844 });
        await page.reload();
        await expect(page.locator('[data-line="1"]')).toBeVisible();
        expect(await fits()).toBe(true);

        await page.goto(`/pembelian?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText(run.supplier);
        expect(await fits()).toBe(true);

        if (width >= 768) {
            await expect(page.getByRole('table', { name: 'Pembelian' })).toBeVisible();
        } else {
            await expect(page.getByRole('table', { name: 'Pembelian' })).toHaveCount(0);
        }

        await page.goto(`/pembelian/baru?perusahaan=${run.company}`);
        await expect(page.getByRole('button', { name: 'Simpan Pembelian' })).toBeVisible();
        expect(await fits()).toBe(true);
        await page.goto(detail);
        await expect(page.getByRole('button', { name: 'Batalkan Pembelian' })).toBeVisible();
    });
}
