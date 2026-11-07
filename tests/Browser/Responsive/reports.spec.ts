import { expect, test } from '@playwright/test';

import { reportRun } from '../report-fixtures';

// TL-11 (TASK-060; NAV-051; NAV-052 `[desk]`, dense and readable on the phone; NAV-053 `[both]`; PT-05; PT-06): PG-051,
// PG-052 and PG-053 without a sideways scroll of the page at every band edge — a wide report table scrolls inside its
// own region.

test.describe.configure({ timeout: 300_000 });

for (const width of [320, 390, 768, 1280]) {
    test(`shows PG-051, PG-052 and PG-053 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await reportRun(page, `laporan-lebar-${String(width)}-${String(Date.now())}`);

        await page.goto(`/laporan?laporan=penjualan&perusahaan=${run.code}`);
        await expect(page.locator('[data-table="penjualan"]')).toContainText('Rp1.000.000');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.goto(`/laporan/gabungan?perusahaan=${run.code}`);
        await expect(page.locator('[data-table="angka"]')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.goto('/laporan/ekspor');
        await expect(page.getByRole('heading', { name: 'Ekspor Saya' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
