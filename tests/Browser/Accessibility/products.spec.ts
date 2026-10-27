import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-12 (TASK-027; PG-056): the catalogue's list with its lookup, the creation sheet and a product's record — units,
// barcodes, policy, price defaults, images and stock — pass the WCAG 2.2 AA checks at both bands, each at rest.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function noViolation(page: Page): Promise<void> {
    await atRest(page);
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
}

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-056 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `produk-a11y-${String(width)}-${String(Date.now())}`);
        await numberingCompany(page, null);
        const name = `Produk Aksesibel ${String(Date.now())}`;

        await page.goto('/data-master/produk');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Produk & Jasa');
        await noViolation(page);

        await page.getByRole('combobox', { name: 'Cari produk atau jasa' }).fill(name);
        await page.getByRole('button', { name: 'Buat Produk' }).click();
        const sheet = page.getByRole('dialog', { name: 'Tambah Produk' });
        await sheet.getByLabel(/^Kode satuan/).fill('PCS');
        await noViolation(page);
        await sheet.getByRole('button', { name: 'Tambah Produk' }).click();
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);
        await noViolation(page);
    });
}
