import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { quotationRun } from '../quotation-fixtures';

// TL-12 (TASK-033; PG-014, PG-037; UXS-41): the quotation's draft in the composer, its sent revision with "Catat Jawaban
// Klien" and its confirmation, and the list pass the WCAG 2.2 AA checks at both bands, each measured at rest.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-014 and PG-037 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await quotationRun(page, `penawaran-a11y-${String(width)}-${String(Date.now())}`, { stage: 'draft' });

        await page.goto(`/proyek/${run.project}/penawaran`);
        await expect(page.locator('[data-slot="document-composer"]')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/dokumen/penawaran?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText(run.number);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}

test('finds no WCAG 2.2 AA violation on a sent quotation and its answer confirmation', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await quotationRun(page, `penawaran-a11y-terkirim-${String(Date.now())}`, { stage: 'sent' });

    await page.goto(`/proyek/${run.project}/penawaran`);
    await expect(page.locator('#konten')).toContainText(/UJI\/PNW\/\d{4}\/0001/);
    await atRest(page);
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

    await page.getByRole('button', { name: 'Catat Jawaban Klien' }).first().click();
    await expect(page.getByRole('alertdialog')).toBeVisible();
    await atRest(page);
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
});
