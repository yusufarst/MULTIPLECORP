import { expect as base, test } from '@playwright/test';

import { clientReturnRun } from '../client-return-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-048; NV-06; `[both]`): PG-034's lines and its return form in one column without a sideways scroll at the phone and tablet widths.

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 768]) {
    test(`shows PG-034 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await clientReturnRun(page, `retur-lebar-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await page.goto(`/proyek/${run.project_id}/retur`);
        await expect(page.locator('[data-slot="return-lines"]')).toBeVisible();
        await expect(page.locator('[data-slot="return-form"]')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
