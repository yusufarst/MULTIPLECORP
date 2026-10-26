import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { clearGoLive, numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-12 (TASK-021): PG-061 with its scheme form and the start's confirmation — its statements and their one required
// tick — passes the WCAG 2.2 AA checks at the phone and desktop bands.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.afterEach(async ({ page }) => {
    await clearGoLive(page);
});

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-061 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `pen-a11y-${String(width)}-${String(Date.now())}`);
        const company = await numberingCompany(page, '2025-01-01');

        await page.goto(`/pengaturan/penomoran?perusahaan=${company}&jenis=doc-06`);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Penomoran Dokumen');
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        if (width >= 768) {
            await page.getByRole('button', { name: 'Tambah Format Nomor' }).click();
            await page.getByLabel('Templat nomor').fill('UJI/{tahun}/{nomor:3}');
            await page.getByRole('button', { name: 'Simpan Format Nomor' }).click();
            await expect(page.getByText('Format nomor Invoice disimpan.')).toBeVisible();
            const year = String(new Date(Date.now() + 7 * 3_600_000).getUTCFullYear());
            await page.getByLabel(`Nomor pertama periode ${year}`).fill('5');
            await page.getByRole('button', { name: 'Mulai Penomoran' }).click();
            await expect(page.getByRole('alertdialog')).toBeVisible();
            expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
        }
    });
}
