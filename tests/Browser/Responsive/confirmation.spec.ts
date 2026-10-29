import { expect, test } from '@playwright/test';

import { confirmationRun } from '../confirmation-fixtures';

// TL-11 (TASK-034; NAV-011 `[both]`; J-FUL-01): PG-011's confirmed lines with their progress read on the phone and the
// desktop without a sideways scroll at every band edge.

for (const width of [320, 390, 768, 1280]) {
    test(`shows PG-011's confirmed lines without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await confirmationRun(page, `konfirmasi-lebar-${String(width)}-${String(Date.now())}`, { confirmed: true });

        await page.goto(`/proyek/${run.project}`);
        await expect(page.getByRole('list', { name: 'Baris terkonfirmasi' }).getByRole('listitem')).toHaveCount(2);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
