import { expect, test } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-11 (TASK-028; PG-057 `[desk]`, readable on the phone — NV-06): the racks and minimums at every band without a
// sideways scroll.

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`lays PG-057 out without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `lokasi-resp-${String(width)}-${String(Date.now())}`);
        await numberingCompany(page, null);

        await page.goto('/data-master/lokasi-rak');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
