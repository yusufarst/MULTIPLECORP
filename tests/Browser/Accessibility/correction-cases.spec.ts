import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { correctionCases } from '../case-fixtures';
import { atRest } from '../evidence-fixtures';

// TL-12 (TASK-031; PG-040): the cases with their residuals and the closure's dialog pass the WCAG 2.2 AA checks at both
// bands, each measured at rest.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-040 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const { company } = await correctionCases(page, `kasus-a11y-${String(width)}-${String(Date.now())}`);

        await page.goto(`/dokumen/kasus?perusahaan=${company}`);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Kasus Koreksi');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.locator('#konten').getByRole('region', { name: 'Retur penjualan' }).getByRole('button', { name: 'Tutup Kasus' }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
