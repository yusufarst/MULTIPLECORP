import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-12 (TASK-026; PG-054; PG-055): the party masters — each list with its lookup, a client with its unit, address and
// PIC, a supplier with its details and price history, and the inline creation sheet — pass the WCAG 2.2 AA checks at
// both bands, each measured at rest.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function noViolation(page: Page): Promise<void> {
    await atRest(page);
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
}

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-054 and PG-055 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `pihak-a11y-${String(width)}-${String(Date.now())}`);
        await numberingCompany(page, null);
        const stamp = String(Date.now());

        await page.goto('/__fixtures/formulir-pihak');
        await page.getByTestId('project-form').getByRole('combobox', { name: 'Klien' }).fill(`Klien Aksesibel ${stamp}`);
        await page.getByRole('button', { name: 'Buat Klien' }).click();
        const sheet = page.getByRole('dialog', { name: 'Buat Klien' });
        await sheet.getByLabel(/^Nama unit/).fill('Unit Aksesibel');
        await sheet.getByLabel(/^Alamat/).fill('Jl. Aksesibel 1');
        await sheet.getByLabel(/^Nama PIC/).fill('PIC Aksesibel');
        await noViolation(page);
        await sheet.getByRole('button', { name: 'Buat Klien' }).click();
        await expect(page.getByTestId('client-chosen').getByRole('listitem')).toHaveCount(4);

        await page.goto('/data-master/klien');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Klien');
        await noViolation(page);
        await page.getByRole('combobox', { name: 'Cari klien' }).fill(`Klien Aksesibel ${stamp}`);
        await page.getByRole('option', { name: `Klien Aksesibel ${stamp}` }).click();
        await expect(page.getByRole('heading', { name: 'Unit Aksesibel' })).toBeVisible();
        await noViolation(page);

        await page.goto('/data-master/pemasok');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pemasok');
        await noViolation(page);
    });
}
