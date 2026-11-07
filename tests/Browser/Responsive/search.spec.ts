import { expect as base, test } from '@playwright/test';

import { stockRun } from '../stock-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-059; NAV-009; D-IA-04): PG-009 without a sideways scroll, full-screen on the phone with the field first.

test.describe.configure({ timeout: 300_000 });

for (const width of [320, 390, 768, 1280]) {
    test(`shows PG-009 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await stockRun(page, `cari-lebar-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await page.goto('/cari');
        await page.locator('#konten').getByRole('textbox', { name: 'Cari atau pindai kode' }).fill(run.sku);
        await expect(page.locator('[data-slot="search-exact"]')).toContainText(run.name);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
