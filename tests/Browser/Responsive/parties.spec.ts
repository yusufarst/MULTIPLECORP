import { expect, test } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-11 (TASK-026; PG-054 `[both]`, search-first; PG-055 `[desk]`, readable on the phone — NV-06; J-MD-02): both lists, a
// client's units and the inline creation's one-column form at every band without a sideways scroll; on the phone the
// lookup and its "Buat Klien" carry the creation.

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`lays PG-054 and PG-055 out without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `pihak-resp-${String(width)}-${String(Date.now())}`);
        await numberingCompany(page, null);
        const name = `Klien Lebar ${String(width)} ${String(Date.now())}`;

        await page.goto('/data-master/klien');
        await page.getByRole('combobox', { name: 'Cari klien' }).fill(name);
        await page.getByRole('button', { name: 'Buat Klien' }).filter({ visible: true }).last().click();
        const sheet = page.getByRole('dialog', { name: 'Buat Klien' });
        await sheet.getByLabel(/^Nama unit/).fill('Unit dengan nama yang cukup panjang untuk diuji');
        await sheet.getByLabel(/^Alamat/).fill('Jl. Panjang Sekali Nomor 123, Kelurahan Uji, Kecamatan Uji');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await sheet.getByRole('button', { name: 'Buat Klien' }).click();
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);

        for (const path of ['/data-master/klien', page.url(), '/data-master/pemasok']) {
            await page.goto(path);
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        }
    });
}
