import { expect, test } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-10 (TASK-027; LOCALIZATION §3; DOD-16): PG-056 and its label in both languages — keys of the catalogue, never
// hardcoded copy.

for (const [locale, texts] of [
    ['id', { title: 'Produk & Jasa', create: 'Tambah Produk', search: 'Cari produk atau jasa', units: 'Satuan', print: 'Buat Barcode Internal' }],
    ['en', { title: 'Products and services', create: 'Add Product', search: 'Search products and services', units: 'Units', print: 'Create Internal Barcode' }],
] as const) {
    test(`shows PG-056 in ${locale}`, async ({ page }) => {
        await ownerSignedIn(page, `produk-${locale}-${String(Date.now())}`, locale);
        await numberingCompany(page, null);

        await page.goto('/data-master/produk');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.title);
        await expect(page.getByRole('combobox', { name: texts.search })).toBeVisible();
        await page.getByRole('button', { name: texts.create }).first().click();
        const sheet = page.getByRole('dialog', { name: texts.create });
        await sheet.getByRole('textbox').first().fill(`L10n ${String(Date.now())}`);
        await sheet.getByLabel(locale === 'id' ? /^Kode satuan/ : /^Unit code/).fill('PCS');
        await sheet.getByRole('button', { name: texts.create }).click();
        await expect(page.getByRole('heading', { name: texts.units })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.print })).toBeVisible();
    });
}
