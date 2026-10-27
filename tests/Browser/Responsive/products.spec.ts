import { expect, test } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-11 (TASK-027; PG-056 `[desk]`, readable on the phone — NV-06; J-MD-04): the catalogue and a product's record at
// every band without a sideways scroll.

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`lays PG-056 out without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `produk-resp-${String(width)}-${String(Date.now())}`);
        await numberingCompany(page, null);
        const name = `Produk dengan nama yang cukup panjang ${String(width)} ${String(Date.now())}`;

        await page.goto('/data-master/produk');
        await page.getByRole('combobox', { name: 'Cari produk atau jasa' }).fill(name);
        await page.getByRole('button', { name: 'Buat Produk' }).click();
        const sheet = page.getByRole('dialog', { name: 'Tambah Produk' });
        await sheet.getByLabel(/^Kode satuan/).fill('PCS');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await sheet.getByRole('button', { name: 'Tambah Produk' }).click();
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);

        for (const path of ['/data-master/produk', page.url()]) {
            await page.goto(path);
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        }
    });
}
