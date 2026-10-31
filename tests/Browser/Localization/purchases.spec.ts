import { expect as base, test } from '@playwright/test';

import { purchaseRun } from '../purchase-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-037; LOCALIZATION §3; §3.4; DOD-16): PG-015–PG-017 in both languages, keys of the catalogue and never hardcoded copy; DOC-02's paper stays Indonesian.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { list: 'Pembelian', create: 'Buat Pembelian', save: 'Simpan Pembelian', reason: 'Pilih pemasok dan isi jumlah serta harga setiap baris.', other: 'Rincian lain', remaining: 'Sisa diterima', po: 'Terbitkan PO' }],
    ['en', { list: 'Purchases', create: 'New purchase', save: 'Save purchase', reason: 'Choose the supplier and enter each line\'s quantity and price.', other: 'Other details', remaining: 'Remaining to receive', po: 'Issue PO' }],
] as const) {
    test(`shows PG-015 and PG-017 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await purchaseRun(page, `beli-${locale}-${String(Date.now())}`, locale, true);

        await page.goto(`/pembelian?perusahaan=${run.company}`);
        await expect(page.getByRole('heading', { name: texts.list, exact: true })).toBeVisible();

        await page.goto(`/pembelian/baru?perusahaan=${run.company}`);
        await expect(page.getByRole('heading', { name: texts.create, exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.save })).toBeDisabled();
        await expect(page.getByText(texts.reason)).toBeVisible();
        await expect(page.getByRole('heading', { name: texts.other })).toBeVisible();

        await page.goto(`/pembelian/${run.purchase ?? ''}`);
        await expect(page.locator('[data-line="1"]')).toContainText(texts.remaining);
        await page.getByRole('button', { name: texts.po }).click();
        await page.waitForURL('**/dokumen/**');
        await expect(page.locator('#konten')).toContainText('Pesanan Pembelian');
    });
}
