import { expect as base, test } from '@playwright/test';

import { creditRun } from '../credit-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-053; NV-06): PG-048's credit, sources and projects in one column without a sideways scroll at the phone and tablet widths.

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 768]) {
    test(`shows PG-048 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await creditRun(page, `kredit-lebar-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await page.goto('/keuangan/kredit-pelanggan');
        await expect(page.locator('[data-slot="credit-group"]').filter({ hasText: run.client_name })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
