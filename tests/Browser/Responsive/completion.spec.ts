import { expect as base, test } from '@playwright/test';

import { completionRun } from '../completion-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-055; NV-06): PG-012's conditions and the Force Complete section in one column without a sideways scroll at the phone and tablet widths.

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 768]) {
    test(`shows PG-012 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await completionRun(page, `selesai-lebar-${String(width)}-${String(Date.now())}`, false);
        await page.setViewportSize({ width, height: 844 });

        await page.goto(`/proyek/${run.project}/penyelesaian`);
        await expect(page.locator('[data-slot="completion-condition"]')).toHaveCount(10);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
