import { expect, test } from '@playwright/test';

import { evidenceCompany } from '../evidence-fixtures';
import { ownerSignedIn } from '../numbering-fixtures';

// TL-11 (TASK-030; PG-038 `[both]`; IP-16; mobile-first): the evidence list and the upload sheet at every band without a
// sideways scroll, "Unggah" reachable on the phone where the header's primary action is not shown.

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`lays PG-038 and its upload sheet out without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `bukti-resp-${String(width)}-${String(Date.now())}`);
        const { company } = await evidenceCompany(page);

        await page.goto(`/dokumen/bukti?cabang=${company}`);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Bukti');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.getByRole('button', { name: 'Unggah', exact: true }).filter({ visible: true }).first().click();
        await expect(page.getByRole('dialog', { name: 'Unggah Dokumen' }).getByRole('button', { name: 'Pilih File' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
