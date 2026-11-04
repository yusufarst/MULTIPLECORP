import { expect as base, test } from '@playwright/test';

import { importRun } from '../import-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-057; NV-06): PG-058 `[desk]`, readable on the phone and the tablet without a sideways scroll, its preparation sheet too.

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 768]) {
    test(`shows PG-058 and its preparation sheet without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await importRun(page, `impor-lebar-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await page.goto('/data-master/impor');
        await expect(page.getByRole('heading', { name: 'Impor Data Awal', level: 1 })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.getByRole('button', { name: 'Siapkan Batch', exact: true }).first().click();
        await expect(page.getByRole('dialog', { name: 'Siapkan Batch' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
