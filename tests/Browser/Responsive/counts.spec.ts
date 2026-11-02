import { expect as base, test } from '@playwright/test';

import { adjustmentRun } from '../adjustment-fixtures';
import { countLine, openCount } from '../count-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-043; NV-06): PG-026 and PG-029 without a sideways scroll, at the phone and tablet widths.

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 768]) {
    test(`shows PG-026 and PG-029 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await adjustmentRun(page, `opname-lebar-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await openCount(page, run.product, run.rack);
        await countLine(page, '11');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.goto('/gudang/selisih-temuan');
        await expect(page.locator('[data-slot="discrepancy-findings"]')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
