import { expect, test } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-11 (TASK-025; PG-059 and PG-062 `[desk]`, readable on the phone — NV-06; J-MD-01): the company master and the tax
// configuration at every band without a sideways scroll, the editing controls on the desktop only.

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`lays PG-059 and PG-062 out without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `perusahaan-resp-${String(width)}-${String(Date.now())}`);
        const company = await numberingCompany(page, null);

        for (const path of [`/pengaturan/perusahaan?perusahaan=${company}&bagian=rekening`, '/pengaturan/pajak?bagian=rate_version']) {
            await page.goto(path);
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        }

        await expect(page.getByRole('button', { name: 'Tambah Tarif' }).filter({ visible: true })).toHaveCount(width < 768 ? 0 : 1);
    });
}
