import { expect, test } from '@playwright/test';

import { correctionCases } from '../case-fixtures';

// TL-11 (TASK-031; PG-040 `[desk]`, readable on the phone): the cases without a sideways scroll at every band edge.

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`shows PG-040 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const { company } = await correctionCases(page, `kasus-lebar-${String(width)}-${String(Date.now())}`);

        await page.goto(`/dokumen/kasus?perusahaan=${company}`);
        await expect(page.getByRole('region', { name: 'Retur penjualan' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
