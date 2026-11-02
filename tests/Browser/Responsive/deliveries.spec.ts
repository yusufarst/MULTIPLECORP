import { expect as base, test } from '@playwright/test';

import { deliveryRun } from '../delivery-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-045; NV-06): PG-031's form in one column without a sideways scroll at the phone and tablet widths.

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 768]) {
    test(`shows PG-031's delivery form without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await deliveryRun(page, `kirim-lebar-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await page.goto(`/gudang/pengiriman/${run.shipment}`);
        await expect(page.locator('[data-slot="delivery-form"]')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
