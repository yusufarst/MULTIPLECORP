import { expect, test } from '@playwright/test';

import { administrationRun } from '../administration-fixtures';

// TL-11 (TASK-035; NAV-039 `[desk]`; J-ADM-01; PT-05; PT-06; PT-29): PG-011's checklist and PG-039 — its table on the
// desktop, its dense rows on the phone — without a sideways scroll at every band edge.

for (const width of [320, 390, 768, 1280]) {
    test(`shows the checklist and PG-039 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await administrationRun(page, `administrasi-lebar-${String(width)}-${String(Date.now())}`);

        await page.goto(`/proyek/${run.project}`);
        await expect(page.getByRole('list', { name: 'Persyaratan administrasi' }).getByRole('listitem')).toHaveCount(3);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.goto(`/dokumen/administrasi?cabang=${run.company}`);
        await expect(page.getByRole('heading', { name: 'Dokumen Administrasi' })).toBeVisible();
        await expect(page.locator('#konten')).toContainText(run.number);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
