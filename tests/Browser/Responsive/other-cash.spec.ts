import { expect as base, test } from '@playwright/test';

import { otherCashRun } from '../other-cash-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-054; NV-06): PG-049's actions and list readable on the phone and the tablet without a sideways scroll.

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 768]) {
    test(`shows PG-049 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await otherCashRun(page, `kas-lain-lebar-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await page.goto(`/keuangan/kas-lain?perusahaan=${run.company}`);
        await expect(page.getByRole('button', { name: 'Catat Pengeluaran Kas', exact: true })).toBeVisible();
        await expect(page.getByText('Belum ada pengeluaran kas.')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
