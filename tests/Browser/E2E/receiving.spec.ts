import { expect as base, test } from '@playwright/test';

import { currentLine, fillLine, receivingRun, record, scan } from '../receiving-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-039; J-INV-01; CJ-01; CJ-07; INT-181–INT-183; INT-006; IP-04; IP-15; IP-19; UXS-34; UXS-35): the journey on the phone and the desktop with the scanner flow — an unknown code, the manual lookup, a duplicate serial — each line's own answer, the Nota's draft, the same-action reservation, an unknown outcome retried under one command, the reversal on PG-016, MSG-35 before leaving.

test.describe.configure({ timeout: 420_000 });

for (const [name, width, height] of [['phone', 390, 844], ['desktop', 1280, 900]] as const) {
    test(`J-INV-01 on the ${name}: from the worklist, a scan opening the line, recorded in two receipts, the Nota's draft on PG-036 (CJ-07; criteria 1, 7, 14, 15)`, async ({ page }) => {
        await page.setViewportSize({ width, height });
        const run = await receivingRun(page, `terima-${name}-${String(Date.now())}`);

        await page.goto('/gudang/barang-masuk');
        await expect(page.getByRole('heading', { name: 'Barang Masuk', exact: true })).toBeVisible();
        await expect(page.locator('#konten')).toContainText(run.supplier);
        await expect(page.locator('#konten')).not.toContainText('Rp');
        await page.locator('#konten').locator(`a[href="/gudang/barang-masuk/${run.purchase}"]:visible`).first().click();
        await page.waitForURL(`**/gudang/barang-masuk/${run.purchase}`);
        await expect(page.getByRole('heading', { name: 'Terima Barang', exact: true })).toBeVisible();
        await expect(page.locator('#konten')).not.toContainText('Rp');

        const field = page.getByRole('textbox', { name: 'Pindai atau ketik kode' });
        await expect(field).toBeFocused();

        const unknown = `KODE-${String(Date.now())}`;
        await scan(page, unknown);
        await expect(page.getByRole('alert').filter({ hasText: `Kode ${unknown} tidak dikenal.` }).locator('visible=true')).toBeVisible();
        await expect(field).toHaveValue('');
        await expect(field).toBeFocused();

        // A scan only looks up: nothing recorded, the line opened.
        await scan(page, run.sku);
        await expect(page.locator('[data-slot="receive-summary"]')).toHaveCount(0);
        await expect(page.getByRole('status').filter({ hasText: 'dibuka' }).locator('visible=true')).toBeVisible();

        // "Daftarkan Barcode": its own command in place, the unsent input kept, and the scan continues (ROUTE-147; IP-15).
        await currentLine(page).getByRole('textbox', { name: 'Ditolak' }).fill('1');
        await scan(page, unknown);
        await page.getByRole('button', { name: 'Daftarkan Barcode' }).click();
        const register = page.getByRole('alertdialog', { name: 'Daftarkan Barcode' });
        await expect(register).toContainText(unknown);
        await register.getByRole('button', { name: 'Daftarkan Barcode' }).click();
        await expect(register).toHaveCount(0);
        await expect(page.getByRole('status').filter({ hasText: 'dibuka' }).locator('visible=true')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Daftarkan Barcode' })).toHaveCount(0);
        await expect(currentLine(page).getByRole('textbox', { name: 'Ditolak' })).toHaveValue('1');
        await currentLine(page).getByRole('textbox', { name: 'Ditolak' }).fill('');

        await page.getByRole('button', { name: 'Cari Produk' }).click();
        await page.getByRole('textbox', { name: 'Cari Produk' }).fill('tidak-ada-barang');
        await expect(page.locator('[data-slot="receive-lines"] li')).toHaveCount(0);
        await page.getByRole('button', { name: 'Cari Produk' }).click();
        await expect(page.locator('[data-slot="receive-lines"] li')).toHaveCount(1);

        await fillLine(page, '4', run.rack);
        await currentLine(page).getByRole('textbox', { name: 'Nomor surat jalan pemasok' }).fill('SJ-1');
        await record(page);
        await expect(currentLine(page).getByText('Barang masuk tercatat.')).toBeVisible();
        await expect(page.locator('[data-slot="receive-progress"]')).toHaveText('0 baris tercatat · 1 baris tersisa');
        await expect(currentLine(page)).toContainText('Sisa diterima 6');

        await fillLine(page, '6', run.rack);
        await record(page);
        await expect(page.locator('[data-slot="receive-progress"]')).toHaveText('1 baris tercatat · 0 baris tersisa');
        const summary = page.locator('[data-slot="receive-summary"]');
        await expect(summary.getByRole('listitem')).toHaveCount(2);
        await expect(summary.getByRole('link', { name: 'Selesai' })).toHaveAttribute('href', '/pekerjaan');

        await summary.getByRole('button', { name: 'Terbitkan Nota Terima Barang' }).first().click();
        await page.waitForURL('**/dokumen/**');
        await expect(page.getByRole('heading', { name: 'Nota Terima Barang', level: 1 })).toBeVisible();

        // On the phone Edit → Preview → Finalize, the floor step (DC-11; AN-6).
        if (name === 'phone') {
            await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'phone');
            await page.getByRole('button', { name: 'Pratinjau', exact: true }).click();
        }

        const paper = page.getByRole('region', { name: /^Pratinjau Nota Terima Barang/ });
        await expect(paper).toContainText('SJ-1');
        await expect(paper).toContainText(run.supplier);
        await expect(paper).not.toContainText('Rp');

        if (name === 'phone') {
            await page.getByRole('button', { name: 'Lanjut' }).click();
        }

        await page.locator('#konten').getByRole('button', { name: 'Terbitkan Nota Terima Barang' }).click();
        const confirm = page.getByRole('alertdialog', { name: 'Terbitkan Nota Terima Barang?' });
        await expect(confirm).toBeVisible();
        await confirm.getByRole('button', { name: 'Terbitkan Nota Terima Barang' }).click();
        await expect(page.locator('#konten')).toContainText('UJI/NTB/');
    });
}

test('counts a serial scanned twice once, refuses one in stock at the scan, marks one damaged (CJ-07; BR-INV-09; MSG-36; criterion 6)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await receivingRun(page, `terima-seri-${String(Date.now())}`);
    const serial = `SN-UJI-${String(Date.now())}`;

    await page.goto(`/gudang/barang-masuk/${run.serial_purchase}`);
    await expect(currentLine(page)).toContainText(run.serial_name);
    // Serials are single units: no alternate unit to choose.
    await expect(currentLine(page).getByRole('combobox', { name: 'Satuan' })).toHaveCount(0);
    await scan(page, serial);
    await expect(currentLine(page).locator('[data-slot="line-serials"]')).toContainText(serial);
    await scan(page, serial);
    await expect(page.getByRole('alert').filter({ hasText: `Nomor seri ${serial} sudah dipindai.` }).locator('visible=true')).toBeVisible();
    await expect(currentLine(page).locator('[data-slot="line-serials"] li')).toHaveCount(1);

    await currentLine(page).getByRole('combobox', { name: 'Rak' }).selectOption({ index: 1 });
    await record(page);
    await expect(currentLine(page).getByText('Barang masuk tercatat.')).toBeVisible();

    await scan(page, serial);
    await expect(page.getByRole('alert').filter({ hasText: `Nomor seri ${serial} sudah ada di stok.` }).locator('visible=true')).toBeVisible();
    await expect(currentLine(page).locator('[data-slot="line-serials"] li')).toHaveCount(0);
});

test('commits the receipt and "Reservasi untuk Proyek Ini" as one command at the receive step (CJ-01; D-UX-12; criterion 9)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await receivingRun(page, `terima-proyek-${String(Date.now())}`);

    await page.goto(`/gudang/barang-masuk/${run.project_purchase}`);
    await expect(page.locator('#konten')).toContainText(run.project_number);
    const line = currentLine(page);
    await expect(line).toContainText(/Maksimal \d+ unit/);
    await fillLine(page, '4', run.rack);
    await line.getByRole('textbox', { name: 'Reservasi untuk Proyek Ini' }).fill('3');
    await record(page);
    await expect(line.getByText('Barang masuk tercatat.')).toBeVisible();

    await page.goto('/gudang/reservasi');
    await expect(page.locator('#konten')).toContainText(run.project_number);
});

test('freezes a line on an unknown outcome with "Coba Lagi" under the same command, one effect (IP-04; INT-017; M-10; criterion 15)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await receivingRun(page, `terima-putus-${String(Date.now())}`);
    await page.goto(`/gudang/barang-masuk/${run.purchase}`);

    let cut = true;
    await page.route('**/gudang/barang-masuk/*/terima', async (route) => {
        if (cut && route.request().method() === 'POST') {
            cut = false;
            await route.fetch();
            await route.abort('connectionreset');

            return;
        }

        await route.continue();
    });
    await fillLine(page, '4', run.rack);
    await record(page);

    const line = currentLine(page);
    await expect(line.getByRole('button', { name: 'Coba Lagi' })).toBeVisible();
    await expect(line.getByRole('textbox', { name: 'Layak pakai' })).toBeDisabled();
    await line.getByRole('button', { name: 'Coba Lagi' }).click();
    await expect(line.getByText('Barang masuk tercatat.')).toBeVisible();

    await page.reload();
    await expect(page.locator('[data-slot="receive-summary"]').getByRole('listitem')).toHaveCount(1);
    await expect(currentLine(page)).toContainText('Sisa diterima 6');
});

test('reverses a receipt on PG-016 with its reason, the remainder open again (INT-183; AX-05; IP-09; criterion 11)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await receivingRun(page, `terima-balik-${String(Date.now())}`);
    await page.goto(`/gudang/barang-masuk/${run.purchase}`);
    await fillLine(page, '4', run.rack);
    await record(page);
    await expect(currentLine(page).getByText('Barang masuk tercatat.')).toBeVisible();

    await page.goto(`/pembelian/${run.purchase}`);
    await expect(page.getByRole('link', { name: 'Terima Barang' })).toHaveAttribute('href', `/gudang/barang-masuk/${run.purchase}`);
    await page.locator('[data-slot="purchase-receipts"]').getByRole('button', { name: 'Balikkan Penerimaan' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Balikkan penerimaan ini?' });
    const confirm = dialog.getByRole('button', { name: 'Balikkan Penerimaan' });
    await expect(confirm).toBeDisabled();
    await dialog.getByRole('textbox').last().fill('Barang yang datang salah');
    await confirm.click();
    await expect(dialog).toHaveCount(0);
    await expect(page.locator('[data-slot="purchase-receipts"]').getByRole('button', { name: 'Balikkan Penerimaan' })).toHaveCount(0);

    await page.goto(`/gudang/barang-masuk/${run.purchase}`);
    await expect(currentLine(page)).toContainText('Sisa diterima 10');
});

test('asks MSG-35 before leaving with an unsent line, keeping it on staying (NAV-022; NV-05; INT-009)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await receivingRun(page, `terima-tinggal-${String(Date.now())}`);
    await page.goto(`/gudang/barang-masuk/${run.purchase}`);
    await currentLine(page).getByRole('textbox', { name: 'Layak pakai' }).fill('4');

    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Pembelian' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Isian yang belum dikirim' });
    await expect(dialog.getByText('Isian yang belum dikirim akan hilang. Tetap tinggalkan halaman?')).toBeVisible();
    await dialog.getByRole('button', { name: 'Kembali' }).click();
    await expect(page).toHaveURL(new RegExp(`/gudang/barang-masuk/${run.purchase}$`));
    await expect(currentLine(page).getByRole('textbox', { name: 'Layak pakai' })).toHaveValue('4');

    // "Tinggalkan Halaman" goes, asked once.
    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Pembelian' }).click();
    await dialog.getByRole('button', { name: 'Tinggalkan Halaman' }).click();
    await page.waitForURL('**/pembelian');
    await expect(page.getByRole('heading', { name: 'Pembelian', exact: true })).toBeVisible();
});

test('reverses one chosen serial on PG-016, the other staying in stock (CM-03; C-02; criterion 11)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await receivingRun(page, `terima-balik-seri-${String(Date.now())}`);
    const first = `SN-BALIK-${String(Date.now())}-1`;
    const second = `SN-BALIK-${String(Date.now())}-2`;
    await page.goto(`/gudang/barang-masuk/${run.serial_purchase}`);
    await scan(page, first);
    await scan(page, second);
    await expect(currentLine(page).locator('[data-slot="line-serials"] li')).toHaveCount(2);
    await currentLine(page).getByRole('combobox', { name: 'Rak' }).selectOption({ index: 1 });
    await record(page);
    await expect(currentLine(page).getByText('Barang masuk tercatat.')).toBeVisible();

    await page.goto(`/pembelian/${run.serial_purchase}`);
    await page.locator('[data-slot="purchase-receipts"]').getByRole('button', { name: 'Balikkan Penerimaan' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Balikkan penerimaan ini?' });
    await dialog.getByRole('textbox').last().fill('Seri ini salah');
    const confirm = dialog.getByRole('button', { name: 'Balikkan Penerimaan' });
    await expect(confirm).toBeDisabled();
    await dialog.getByRole('checkbox', { name: new RegExp(first) }).check();
    await confirm.click();
    await expect(dialog).toHaveCount(0);

    await page.locator('[data-slot="purchase-receipts"]').getByRole('button', { name: 'Balikkan Penerimaan' }).click();
    const again = page.getByRole('alertdialog', { name: 'Balikkan penerimaan ini?' });
    await expect(again.getByRole('checkbox')).toHaveCount(1);
    await expect(again).toContainText(second);
});

test('stops the scanner at a line whose answer is lost, no serial silently dropped by the retry (IP-02; IP-04; BD-18)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await receivingRun(page, `terima-seri-putus-${String(Date.now())}`);
    const first = `SN-PUTUS-${String(Date.now())}-1`;
    const second = `SN-PUTUS-${String(Date.now())}-2`;
    await page.goto(`/gudang/barang-masuk/${run.serial_purchase}`);
    await scan(page, first);
    await currentLine(page).getByRole('combobox', { name: 'Rak' }).selectOption({ index: 1 });

    let cut = true;
    await page.route('**/gudang/barang-masuk/*/terima', async (route) => {
        if (cut && route.request().method() === 'POST') {
            cut = false;
            await route.fetch();
            await route.abort('connectionreset');

            return;
        }

        await route.continue();
    });
    await record(page);
    const retry = currentLine(page).getByRole('button', { name: 'Coba Lagi' });
    await expect(retry).toBeVisible();

    await scan(page, second);
    await expect(page.getByRole('alert').filter({ hasText: second }).locator('visible=true')).toBeVisible();
    await expect(currentLine(page).locator('[data-slot="line-serials"] li')).toHaveCount(1);

    await retry.click();
    await expect(currentLine(page).getByText('Barang masuk tercatat.')).toBeVisible();
    await scan(page, second);
    await expect(currentLine(page).locator('[data-slot="line-serials"]')).toContainText(second);
});
