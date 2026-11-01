import { expect as base, type Page, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { adjustmentRun, type AdjustmentRun } from '../adjustment-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-042; J-INV-04; J-INV-06; J-MD-05; CJ-05; INT-190; INT-194–INT-196; UXS-15; UXS-22): a condition change and its re-inspection, a disposal with its loss and exact reversal, the move from PG-019, a likely duplicate and a routed loss, on the phone and the desktop.

test.describe.configure({ timeout: 420_000 });

const lot = (page: Page, code: string, condition: string) => page.locator('[data-slot="adjustment-lot"]').filter({ hasText: code }).filter({ hasText: condition }).first();
const fill = async (page: Page, run: AdjustmentRun, condition: string, quantity: string) => {
    await lot(page, run.lot, condition).getByLabel(/Jumlah dari/).fill(quantity);
};

for (const [name, width, height] of [['phone', 390, 844], ['desktop', 1280, 900]] as const) {
    test(`J-INV-04 and J-INV-06 on the ${name}: a change, its re-inspection, a disposal, its reversal and a move, each answered (CJ-05; criteria 1, 3, 5, 6, 11)`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await adjustmentRun(page, `sesuai-${name}-${String(Date.now())}`);
        await page.setViewportSize({ width, height });

        await page.goto(`/gudang/stok/${run.product}`);
        await page.locator('[data-slot="product-commands"]').getByRole('link', { name: 'Ubah Kondisi' }).click();
        await expect(page.getByRole('heading', { name: 'Penyesuaian & Kondisi', exact: true })).toBeVisible();
        await expect(lot(page, run.lot, 'Layak pakai')).toContainText('Ambil Lebih Dulu');
        await fill(page, run, 'Layak pakai', '2');
        await page.getByLabel('Bukti', { exact: true }).selectOption({ index: 1 });
        await page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Ubah Kondisi' }).click();
        const change = page.getByRole('alertdialog');
        await change.getByLabel('Alasan').fill('Penyok saat diperiksa');
        await change.getByRole('button', { name: 'Ubah Kondisi' }).click();
        await page.waitForURL(`**/gudang/stok/${run.product}`);
        await expect(page.locator('#konten')).toContainText('Kondisi unit tercatat');

        // The same again on the same day is a likely duplicate, committed only with the actor's reason (UXS-15).
        await page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=kondisi`);
        await fill(page, run, 'Layak pakai', '2');
        await page.getByLabel('Bukti', { exact: true }).selectOption({ index: 1 });
        await page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Ubah Kondisi' }).click();
        await page.getByRole('alertdialog').getByLabel('Alasan').fill('Penyok saat diperiksa');
        await page.getByRole('alertdialog').getByRole('button', { name: 'Ubah Kondisi' }).click();
        const warning = page.locator('[data-slot="duplicate-warning"]');
        await expect(warning).toBeVisible();
        await warning.getByRole('checkbox').first().check();
        await warning.getByRole('textbox').fill('Dua unit berbeda');
        await page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Ubah Kondisi' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Ubah Kondisi' }).click();
        await page.waitForURL(`**/gudang/stok/${run.product}`);

        await page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=periksa`);
        await fill(page, run, 'Tidak layak pakai', '1');
        await page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Periksa Ulang' }).click();
        await page.getByRole('alertdialog').getByLabel('Alasan').fill('Masih berfungsi');
        await page.getByRole('alertdialog').getByRole('button', { name: 'Periksa Ulang' }).click();
        await expect(page.locator('#konten')).toContainText('Hasil periksa ulang tercatat');

        await page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=penyesuaian`);
        await expect(page.locator('[data-slot="adjustment-form"]')).toContainText('Kerugian dicatat satu kali');
        await page.getByLabel('Jenis penyesuaian').selectOption({ label: 'Musnahkan' });
        await fill(page, run, 'Tidak layak pakai', '1');
        await page.getByLabel('Bukti', { exact: true }).selectOption({ index: 1 });
        await page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Catat Penyesuaian Stok' }).click();
        await page.getByRole('alertdialog').getByLabel('Alasan').fill('Rusak total');
        await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Penyesuaian Stok' }).click();
        await expect(page.locator('#konten')).toContainText('Penyesuaian stok tercatat');

        await page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=penyesuaian`);
        const record = page.locator('[data-slot="loss-record"]').first();
        await record.getByRole('button', { name: 'Balikkan Kerugian' }).click();
        await page.getByRole('alertdialog').getByLabel('Alasan').fill('Keliru dicatat');
        await page.getByRole('alertdialog').getByRole('button', { name: 'Balikkan Kerugian' }).click();
        await expect(page.getByRole('alertdialog')).toHaveCount(0);
        await expect(page.locator('[data-slot="loss-record"]').first().getByRole('button', { name: 'Balikkan Kerugian' })).toHaveCount(0);

        await page.goto(`/gudang/stok/${run.product}`);
        await page.locator('[data-slot="product-commands"]').getByRole('button', { name: 'Pindah Lokasi' }).click();
        const sheet = page.locator('[data-slot="move-form"]');
        await sheet.getByLabel('Dari').selectOption({ index: 1 });
        await sheet.getByLabel('Ke rak').selectOption({ value: await sheet.getByLabel('Ke rak').locator('option', { hasText: run.target }).getAttribute('value') ?? '' });
        await sheet.getByLabel('Jumlah dipindahkan').fill('1');
        await sheet.getByRole('button', { name: 'Pindah Lokasi' }).click();
        await expect(page.locator('#konten')).toContainText('Unit dipindahkan');
    });
}

test('refuses and routes a loss of another company\'s lot with MSG-14, nothing recorded; a change of it needs one company in scope (UXS-22; AZ-08; criteria 9, 17)', async ({ page, browser }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await adjustmentRun(page, `sesuai-rute-${String(Date.now())}`);
    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
    const admin = await context.newPage();
    await signedIn(admin, 'sesuai-admin', { plain: true });
    expect((await admin.request.post('/__fixtures/adjustments/plain', { data: { company: run.company } })).ok()).toBe(true);

    await admin.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=penyesuaian`);
    const other = admin.locator('[data-slot="adjustment-lot"]').filter({ hasText: 'perusahaan lain' }).first();
    await other.getByLabel(/Jumlah dari/).fill('1');
    await admin.getByLabel('Bukti', { exact: true }).selectOption({ index: 1 });
    await admin.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Catat Penyesuaian Stok' }).click();
    await admin.getByRole('alertdialog').getByLabel('Alasan').fill('Hilang');
    await admin.getByRole('alertdialog').getByRole('button', { name: 'Catat Penyesuaian Stok' }).click();
    await expect(admin.locator('[data-slot="adjustment-form"]')).toContainText('diteruskan');
    await context.close();
});
