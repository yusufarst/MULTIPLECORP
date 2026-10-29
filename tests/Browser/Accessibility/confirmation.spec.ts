import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { confirmationRun } from '../confirmation-fixtures';
import { atRest } from '../evidence-fixtures';

// TL-12 (TASK-034; PG-011's *Item dan pemenuhan*): the unconfirmed project with "Konfirmasi Proyek" and its confirmation,
// and the confirmed lines with "Batalkan Sisa", pass the WCAG 2.2 AA checks at both bands, each measured at rest.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-011's confirmation and fulfilment at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await confirmationRun(page, `konfirmasi-a11y-${String(width)}-${String(Date.now())}`);

        await page.goto(`/proyek/${run.project}`);
        await expect(page.getByRole('heading', { name: 'Item dan pemenuhan' })).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByRole('button', { name: 'Konfirmasi Proyek' }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
        await page.getByRole('alertdialog').getByRole('button', { name: 'Konfirmasi Proyek' }).click();

        await expect(page.getByRole('list', { name: 'Baris terkonfirmasi' }).getByRole('listitem')).toHaveCount(2);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
