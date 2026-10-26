import { expect, test } from '@playwright/test';

import { clearGoLive, numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-11 (TASK-021; NAV-061 `[desk]`; IA §8.6): PG-061 is readable on the phone — its facts and counters, no page
// scroll sideways — and offers its commands on the desktop.

test.afterEach(async ({ page }) => {
    await clearGoLive(page);
});

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`lays PG-061 out without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `pen-resp-${String(width)}-${String(Date.now())}`);
        const company = await numberingCompany(page, '2025-01-01');

        await page.goto(`/pengaturan/penomoran?perusahaan=${company}&jenis=doc-06`);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Penomoran Dokumen');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await expect(page.getByRole('button', { name: 'Tambah Format Nomor' })).toHaveCount(width >= 768 ? 1 : 0);
    });
}
