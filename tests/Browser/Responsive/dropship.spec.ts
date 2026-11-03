import { expect as base, test } from '@playwright/test';

import { dropshipRun } from '../dropship-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-046; NV-06; `[desk]`): PG-032's state readable and its form in one column without a sideways scroll at the phone and tablet widths.

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 768]) {
    test(`shows PG-032 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await dropshipRun(page, `langsung-lebar-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await page.goto(`/proyek/${run.project}/kirim-langsung`);
        await expect(page.locator('[data-slot="dropship-lines"]')).toBeVisible();
        await expect(page.locator('[data-slot="dropship-form"]')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
