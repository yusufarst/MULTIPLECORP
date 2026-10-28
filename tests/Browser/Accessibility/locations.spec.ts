import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-12 (TASK-028; PG-057): the racks, the minimums and the rack's sheet pass the WCAG 2.2 AA checks at both bands, each
// measured at rest.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function noViolation(page: Page): Promise<void> {
    await atRest(page);
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
}

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-057 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `lokasi-a11y-${String(width)}-${String(Date.now())}`);
        await numberingCompany(page, null);

        await page.goto('/data-master/lokasi-rak');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Lokasi Rak');
        await noViolation(page);
        await page.getByRole('button', { name: 'Tambah Rak' }).first().click();
        await expect(page.getByRole('dialog', { name: 'Tambah Rak' })).toBeVisible();
        await noViolation(page);
    });
}
