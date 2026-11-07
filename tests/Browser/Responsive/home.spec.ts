import { expect as base, test } from '@playwright/test';

import { importRun } from '../import-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-058; NV-06; NAV-006–NAV-008): Beranda and Pekerjaan `[both]` and Tinjauan `[desk]`, readable without a
// sideways scroll at the phone and the tablet widths.

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 768]) {
    test(`shows Beranda, Pekerjaan and Tinjauan without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await importRun(page, `beranda-lebar-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        for (const [path, title] of [['/beranda', 'Beranda'], ['/pekerjaan', 'Pekerjaan'], ['/tinjauan', 'Tinjauan']] as const) {
            await page.goto(path);
            await expect(page.getByRole('heading', { name: title, level: 1 })).toBeVisible();
            await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        }
    });
}
