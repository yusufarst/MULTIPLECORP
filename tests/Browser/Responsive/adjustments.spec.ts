import { expect as base, test } from '@playwright/test';

import { adjustmentRun } from '../adjustment-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-042; NV-06): PG-027 and the move without a sideways scroll, at the phone and tablet widths.

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 768]) {
    test(`shows PG-027 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await adjustmentRun(page, `sesuai-lebar-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=penyesuaian`);
        await expect(page.locator('[data-slot="adjustment-lot"]').first()).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.goto(`/gudang/stok/${run.product}`);
        await page.locator('[data-slot="product-commands"]').getByRole('button', { name: 'Pindah Lokasi' }).click();
        await expect(page.locator('[data-slot="move-form"]')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
