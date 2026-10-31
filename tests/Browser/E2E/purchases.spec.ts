import { expect as base, test } from '@playwright/test';

import { addPurchaseLine, chooseSupplier, purchaseRun } from '../purchase-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-037; J-PUR-01; J-PUR-02; CJ-01; CJ-02; INT-171–INT-175; INT-180; UXS-35): a replenishment with and without a PO, a project's shortage bought from two suppliers, the disabled save with its reason, a cancellation and thirty lines by keyboard; no cash or stock from a purchase.

test.describe.configure({ timeout: 420_000 });

const UUID = /\/pembelian\/[0-9a-f-]{36}$/;

test('keeps "Simpan Pembelian" disabled with its reason, saves a replenishment, no cash or stock, then issues its optional PO (J-PUR-02; BR-PUR-03; AC-05; criteria 1, 17, 19)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await purchaseRun(page, `beli-${String(Date.now())}`);

    await page.goto(`/pembelian/baru?perusahaan=${run.company}`);
    const save = page.getByRole('button', { name: 'Simpan Pembelian' });
    await expect(save).toBeDisabled();
    await expect(page.getByText('Pilih pemasok dan isi jumlah serta harga setiap baris.')).toBeVisible();

    await chooseSupplier(page, run.supplier);
    await addPurchaseLine(page, 0, run.goods, '10', '100000');
    await expect(page.locator('#konten')).toContainText('Rp1.000.000');
    await expect(save).toBeEnabled();
    await save.click();

    await page.waitForURL(UUID);
    await expect(page.getByText('Belum ada uang keluar dan belum ada barang masuk.')).toBeVisible();
    await expect(page.locator('#konten')).toContainText('UJI/BELI/');
    await expect(page.locator('#konten')).toContainText(run.supplier);
    await expect(page.locator('#konten')).toContainText('Belum ada penerimaan.');

    await page.getByRole('button', { name: 'Terbitkan PO' }).click();
    await page.waitForURL('**/dokumen/**');
    await expect(page.locator('#konten')).toContainText(run.supplier);
});

test('buys a project\'s shortage prefilled, from two suppliers without duplicating its demand, the company shown and not chosen (J-PUR-01; CJ-01; CJ-02; L-05; criteria 3, 4)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await purchaseRun(page, `beli-proyek-${String(Date.now())}`);

    for (const [supplier, quantity] of [[run.supplier, '4'], [run.other_supplier, '6']] as const) {
        await page.goto(`/pembelian/baru?proyek=${run.project}`);
        await expect(page.locator('#konten')).toContainText(run.project_number);
        await expect(page.locator('#konten')).toContainText(run.code);
        await expect(page.getByLabel(/^Perusahaan/)).toHaveCount(0);
        const row = page.locator('[data-slot="line-editor"] [data-line]').first();
        await expect(row.getByRole('textbox', { name: 'Jumlah' })).toHaveValue(/10/);
        await chooseSupplier(page, supplier);
        await row.getByRole('textbox', { name: 'Jumlah' }).fill(quantity);
        await row.getByRole('textbox', { name: 'Harga' }).fill('90000');
        await page.getByRole('button', { name: 'Simpan Pembelian' }).click();
        await page.waitForURL(UUID);
    }

    await page.goto(`/proyek/${run.project}`);
    const procurement = page.locator('[data-slot="procurement"]');
    await expect(procurement).toContainText(run.supplier);
    await expect(procurement).toContainText(run.other_supplier);
    await expect(procurement).toContainText('Sisa 4');
    await expect(procurement).toContainText('Sisa 6');

    await page.goto(`/pembelian?perusahaan=${run.company}`);
    await expect(page.getByRole('table', { name: 'Pembelian' }).getByRole('row')).toHaveCount(3);
});

test('shows the previous price of the supplier and item, and amends a line before the first effect (SH-01; L-23; criteria 10, 15)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await purchaseRun(page, `beli-ubah-${String(Date.now())}`);

    await page.goto(`/pembelian/baru?perusahaan=${run.company}`);
    await chooseSupplier(page, run.supplier);
    await addPurchaseLine(page, 0, run.goods, '10', '77000');
    await page.getByRole('button', { name: 'Simpan Pembelian' }).click();
    await page.waitForURL(UUID);
    const detail = page.url();

    await page.goto(`/pembelian/baru?perusahaan=${run.company}`);
    await chooseSupplier(page, run.supplier);
    await addPurchaseLine(page, 0, run.goods, '1', '80000');
    await expect(page.getByText(/Harga terakhir Rp77\.000/)).toBeVisible();

    await page.goto(detail);
    await page.getByRole('button', { name: 'Ubah Pembelian' }).click();
    const row = page.locator('[data-slot="line-editor"] [data-line]').first();
    await row.getByRole('textbox', { name: 'Jumlah' }).fill('12');
    await page.getByRole('button', { name: 'Simpan Perubahan' }).click();
    await expect(page.getByText('Pembelian diperbarui.')).toBeVisible();
    await expect(page.locator('[data-line="1"]')).toContainText('Rp924.000');
});

test('cancels with its reason, its number kept; closes a remainder; marks and takes back a non-stock line (AX-12; AX-32; F1; criteria 11, 13, 20)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await purchaseRun(page, `beli-batal-${String(Date.now())}`);

    await page.goto(`/pembelian/baru?proyek=${run.project}`);
    await chooseSupplier(page, run.supplier);
    const prefilled = page.locator('[data-slot="line-editor"] [data-line]').first();
    await prefilled.getByRole('textbox', { name: 'Harga' }).fill('1000');
    await addPurchaseLine(page, 1, run.service, '1', '50000');
    await page.getByRole('button', { name: 'Simpan Pembelian' }).click();
    await page.waitForURL(UUID);
    const number = await page.locator('h1').innerText();

    const line = page.locator('[data-line="2"]');
    await line.getByRole('button', { name: 'Tandai Terlaksana' }).click();
    await expect(line.getByRole('button', { name: 'Batalkan Tanda Terlaksana' })).toBeVisible();
    // A performed line: the cancellation offered no more, the closure in its place (ADMIN_FLOW §11).
    await expect(page.getByRole('button', { name: 'Batalkan Pembelian' })).toHaveCount(0);
    await expect(page.getByText('Pembelian ini tidak dapat dibatalkan.')).toBeVisible();

    await line.getByRole('button', { name: 'Batalkan Tanda Terlaksana' }).click();
    await page.getByRole('alertdialog').getByLabel(/^Alasan/).fill('Salah tandai');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Batalkan Tanda Terlaksana' }).click();
    await expect(line.getByRole('button', { name: 'Tandai Terlaksana' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Batalkan Pembelian' })).toBeVisible();

    await page.getByRole('button', { name: 'Tutup Sisa Pembelian' }).click();
    const closure = page.getByRole('alertdialog');
    await expect(closure).toContainText('Sisa terbuka');
    await closure.getByLabel(/^Alasan/).fill('Pemasok tidak sanggup');
    await closure.getByRole('button', { name: 'Tutup Sisa Pembelian' }).click();
    await expect(page.locator('h1')).toContainText('Sisa Ditutup');
    expect(number).toContain('UJI/BELI/');
});

test('reads and cancels a purchase on the phone, PG-017 readable with its small edits (NV-06; INT-171\'s walk at the phone width)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await purchaseRun(page, `beli-hp-${String(Date.now())}`, 'id', true);

    await page.goto(`/pembelian/baru?perusahaan=${run.company}`);
    await expect(page.getByRole('button', { name: 'Simpan Pembelian' })).toBeDisabled();
    await expect(page.getByText('Pilih pemasok dan isi jumlah serta harga setiap baris.')).toBeVisible();

    await page.goto(`/pembelian/${run.purchase ?? ''}`);
    await page.getByRole('button', { name: 'Batalkan Pembelian' }).click();
    await page.getByRole('alertdialog').getByLabel(/^Alasan/).fill('Tidak jadi');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Batalkan Pembelian' }).click();
    await expect(page.locator('h1')).toContainText('Dibatalkan');
});

test('enters thirty lines by keyboard alone on the desktop (UXS-35; PT-10; criterion 19)', async ({ page }) => {
    test.setTimeout(1_500_000);
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await purchaseRun(page, `beli-30-${String(Date.now())}`);

    await page.goto(`/pembelian/baru?perusahaan=${run.company}`);
    await chooseSupplier(page, run.supplier);

    for (let index = 0; index < 30; index++) {
        const row = page.locator('[data-slot="line-editor"] [data-line]').nth(index);
        const product = row.getByRole('combobox', { name: 'Produk/Jasa' });
        await product.focus();
        await page.keyboard.type(run.goods);
        await expect(page.getByRole('option').first()).toBeVisible();
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Enter');
        await expect(row.getByRole('combobox', { name: 'Satuan' })).toBeEnabled();
        await row.getByRole('textbox', { name: 'Jumlah' }).focus();
        await page.keyboard.type('1');
        await page.keyboard.press('Tab');
        await page.keyboard.press('Tab');
        await page.keyboard.type('1000');
    }

    await expect(page.locator('#konten')).toContainText('Rp30.000');
    const save = page.getByRole('button', { name: 'Simpan Pembelian' });
    await expect(save).toBeEnabled();
    await save.focus();
    await page.keyboard.press('Enter');
    await page.waitForURL(UUID);
    await expect(page.locator('[data-line="30"]')).toBeVisible();
});
