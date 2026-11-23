import { expect as base, type Page, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { adjustmentRun } from '../adjustment-fixtures';
import { applyWith, countLine, openCount } from '../count-fixtures';
import { creditRun } from '../credit-fixtures';
import { deliveryRun } from '../delivery-fixtures';
import { dropshipRun } from '../dropship-fixtures';
import { uniquePdf, uniquePhoto } from '../evidence-fixtures';
import { addPurchaseLine, chooseSupplier, purchaseRun } from '../purchase-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-096 A; GAP-073; IP-16; NV-04; NV-02; MSG-35): evidence registered from a form in place — the sheet over the form,
// the address and every typed value kept, the new evidence chosen in its field —; leaving with unsent input asks MSG-35 in the
// application's own dialog, and nothing asks once the command has answered.

test.describe.configure({ timeout: 420_000 });

type Upload = { name: string; mimeType: string; buffer: Buffer };

/** IP-16's sheet: the file, the issuer and the date — the proven amount where the type bears one —, registered. */
async function register(page: Page, file: Upload, issuer: string, options: { pool?: boolean; proven?: string } = {}): Promise<void> {
    const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
    await sheet.getByLabel('Unggah', { exact: true }).setInputFiles(file);
    await expect(sheet.getByText(file.name)).toBeVisible();

    if (options.pool === true) {
        await sheet.getByRole('checkbox').check();
    }

    await sheet.getByLabel(/^Penerbit\b/).fill(issuer);
    await sheet.getByLabel('Tanggal dokumen').fill('2026-10-01');

    if (options.proven !== undefined) {
        await sheet.getByLabel(/^Jumlah pada bukti/).fill(options.proven);
    }

    await sheet.getByRole('button', { name: 'Daftarkan Bukti' }).click();
    await expect(sheet).toHaveCount(0);
}

test('PG-031 on the phone: the proof registered in place, the delivery\'s input kept and the proof chosen; MSG-35 before leaving, none after the record (IP-16; NV-04; MSG-35)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await deliveryRun(page, `bukti-kirim-${String(Date.now())}`);
    await page.setViewportSize({ width: 390, height: 844 });

    await page.goto(`/gudang/pengiriman/${run.shipment}`);
    const form = page.locator('[data-slot="delivery-form"]');
    const quantity = page.locator('[data-slot="delivery-line"]').first().getByLabel(/^Jumlah/);
    await quantity.fill('2');
    await form.getByLabel('Nama penerima').fill('Bu Sari');
    await form.getByRole('button', { name: 'Unggah Bukti' }).click();
    await register(page, { name: 'foto-terima.png', mimeType: 'image/png', buffer: uniquePhoto() }, 'Kurir Uji 096');

    // The same form, nothing typed lost, the proof just registered chosen (NV-04).
    await expect(page).toHaveURL(new RegExp(`/gudang/pengiriman/${run.shipment}$`));
    await expect(quantity).toHaveValue('2');
    await expect(form.getByLabel('Nama penerima')).toHaveValue('Bu Sari');
    await expect(form.getByLabel('Bukti pengiriman').locator('option:checked')).toContainText('Kurir Uji 096');

    // Leaving with the input unsent asks MSG-35 in the application's dialog; "Kembali" stays with everything kept.
    await form.getByRole('link', { name: 'Batal' }).click();
    const leaving = page.getByRole('alertdialog');
    await expect(leaving).toContainText('Isian yang belum dikirim akan hilang. Tetap tinggalkan halaman?');
    await leaving.getByRole('button', { name: 'Kembali' }).click();
    await expect(page.getByRole('alertdialog')).toHaveCount(0);
    await expect(page).toHaveURL(new RegExp(`/gudang/pengiriman/${run.shipment}$`));
    await expect(form.getByLabel('Nama penerima')).toHaveValue('Bu Sari');

    // The record answers on PG-030, nothing asked on the way.
    await form.getByRole('button', { name: 'Catat Pengiriman', exact: true }).click();
    await page.waitForURL(/\/gudang\/pengiriman$/);
    await expect(page.locator('#konten')).toContainText('Pengiriman tercatat.');
    await expect(page.getByRole('alertdialog')).toHaveCount(0);

    // A paper's draft answers on its own page: with input unsent, MSG-35 first; "Kembali" stays with it kept (P2-1; ER-06).
    await page.goto(`/gudang/pengiriman/${run.shipment}`);
    await form.getByLabel('Nama penerima').fill('Pak Budi');
    await page.locator('[data-slot="delivery-record"]').first().getByRole('button', { name: 'Terbitkan BAST', exact: true }).click();
    await expect(page.getByRole('alertdialog')).toContainText('Isian yang belum dikirim akan hilang. Tetap tinggalkan halaman?');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Kembali' }).click();
    await expect(page.getByRole('alertdialog')).toHaveCount(0);
    await expect(page).toHaveURL(new RegExp(`/gudang/pengiriman/${run.shipment}$`));
    await expect(form.getByLabel('Nama penerima')).toHaveValue('Pak Budi');
});

test('PG-032: the supplier\'s proof and the client\'s registered in place, each into its own field, the confirmation\'s input kept (IP-16; NV-04)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await dropshipRun(page, `bukti-langsung-${String(Date.now())}`);

    await page.goto(`/proyek/${run.project}/kirim-langsung`);
    const form = page.locator('[data-slot="dropship-form"]');
    const shipped = page.locator('[data-slot="dropship-entry"]').first().getByLabel(/^Jumlah dikirim/);
    await form.getByLabel('Nomor pengiriman pemasok').fill('SJ-PMS-096');
    await shipped.fill('2');
    await form.getByRole('button', { name: 'Unggah Bukti' }).first().click();
    await register(page, { name: 'sj-pemasok.pdf', mimeType: 'application/pdf', buffer: uniquePdf() }, 'Pemasok Uji 096');

    await expect(page).toHaveURL(new RegExp(`/proyek/${run.project}/kirim-langsung$`));
    await expect(form.getByLabel('Nomor pengiriman pemasok')).toHaveValue('SJ-PMS-096');
    await expect(shipped).toHaveValue('2');
    await expect(form.getByLabel('Bukti dari pemasok').locator('option:checked')).toContainText('Pemasok Uji 096');

    await form.getByRole('checkbox', { name: /Bukti terima klien sudah ada/ }).click();
    await form.getByLabel('Nama penerima').fill('Bu Sari');
    await form.getByRole('button', { name: 'Unggah Bukti' }).last().click();
    await register(page, { name: 'terima-klien.png', mimeType: 'image/png', buffer: uniquePhoto() }, 'Klien Uji 096');

    await expect(form.getByLabel('Bukti terima klien', { exact: true }).locator('option:checked')).toContainText('Klien Uji 096');
    await expect(form.getByLabel('Bukti dari pemasok').locator('option:checked')).toContainText('Pemasok Uji 096');
    await expect(form.getByLabel('Nama penerima')).toHaveValue('Bu Sari');
    await expect(form.getByLabel('Nomor pengiriman pemasok')).toHaveValue('SJ-PMS-096');
});

test('PG-048: the refund\'s transfer proof registered while its dialog steps aside, the dialog back with its reason and the proof chosen, then records (IP-16; NV-04)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await creditRun(page, `bukti-kredit-${String(Date.now())}`);

    await page.goto('/keuangan/kredit-pelanggan');
    const group = page.locator('[data-slot="credit-group"]').filter({ hasText: run.client_name });
    await group.locator('[data-slot="credit-source"]').first().getByRole('button', { name: 'Kembalikan Dana' }).click();
    const refunding = page.getByRole('alertdialog');
    await refunding.getByLabel('Alasan').fill('Klien meminta dana kembali');

    // Escape closes the sheet alone: the refund's dialog, aside while the sheet is open, comes back with what was typed.
    await refunding.getByRole('button', { name: 'Unggah Bukti' }).click();
    const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
    await expect(sheet).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(sheet).toHaveCount(0);
    await expect(refunding).toBeVisible();
    await expect(refunding.getByLabel('Alasan')).toHaveValue('Klien meminta dana kembali');

    await refunding.getByRole('button', { name: 'Unggah Bukti' }).click();
    await register(page, { name: 'transfer.pdf', mimeType: 'application/pdf', buffer: uniquePdf() }, 'Bank Uji 096');

    await expect(page).toHaveURL(/\/keuangan\/kredit-pelanggan/);
    await expect(refunding).toBeVisible();
    await expect(refunding.getByLabel('Alasan')).toHaveValue('Klien meminta dana kembali');
    await expect(refunding.getByLabel('Bukti transfer').locator('option:checked')).toContainText('Bank Uji 096');
    await refunding.getByRole('button', { name: 'Kembalikan Dana', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Pengembalian dana dicatat.');
});

test('PG-017: a supplier\'s bill registered in place from a charge, the purchase\'s input kept, the chosen company carried in an address that never grows with the lines and the previous price read again (IP-16; NV-04; SH-01)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await purchaseRun(page, `bukti-beli-${String(Date.now())}`, 'id', true);

    await page.goto('/pembelian/baru');
    await page.getByLabel(/^Perusahaan/).selectOption({ value: run.company });
    await chooseSupplier(page, run.supplier);
    await addPurchaseLine(page, 0, run.goods, '2', '260000');
    await expect(page.getByText(/^Harga terakhir/)).toBeVisible();
    await page.getByLabel('Nomor penawaran pemasok').fill('PNW-096');
    await page.getByRole('button', { name: 'Biaya pembelian' }).click();
    await page.getByRole('button', { name: 'Tambah Biaya' }).click();
    await page.getByRole('button', { name: 'Unggah Tagihan' }).click();
    // The return address carries the page's own keys, never the lines' products or the supplier: one size whatever the lines (ER-06).
    const sent = page.waitForRequest((request) => request.method() === 'POST' && new URL(request.url()).pathname === '/bukti');
    await register(page, { name: 'tagihan.pdf', mimeType: 'application/pdf', buffer: uniquePdf() }, 'Pemasok Uji 096', { proven: '50000' });
    const back = String((((await sent).postDataJSON() ?? {}) as { kembali?: unknown }).kembali);
    expect(back).toContain(`perusahaan=${run.company}`);
    expect(back).not.toMatch(/harga|pemasok/);

    await expect(page).toHaveURL(new RegExp(`/pembelian/baru\\?perusahaan=${run.company}`));
    await expect(page.getByLabel(/^Perusahaan/)).toHaveValue(run.company);
    await expect(page.getByLabel('Nomor penawaran pemasok')).toHaveValue('PNW-096');
    const line = page.locator('[data-slot="line-editor"] [data-line]').first();
    await expect(line.getByRole('textbox', { name: 'Jumlah' })).toHaveValue(/^2/);
    await expect(line.getByRole('textbox', { name: 'Harga' })).toHaveValue(/260/);
    await expect(page.getByLabel(/^Tagihan sumber/)).not.toHaveValue('');
    // SH-01's previous price, which the answer's page reads without the lines' products, read again with the supplier and the
    // line the form kept (P3-4; ER-06).
    await expect(page.getByText(/^Harga terakhir/)).toBeVisible();
});

test('PG-029: pool evidence registered in place for a pending case\'s resolution, the quantity typed kept and the evidence chosen (IP-16; NV-04)', async ({ page, browser }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await adjustmentRun(page, `bukti-selisih-${String(Date.now())}`);

    await openCount(page, run.product, run.rack);
    await countLine(page, '11');
    await page.locator('[data-slot="count-split"]').getByLabel('Selisih menunggu penetapan').fill('1');
    await applyWith(page, 'Tidak jelas milik siapa');
    await expect(page.locator('[data-slot="count-detail"]')).toContainText('Diterapkan');

    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
    const admin = await context.newPage();
    await signedIn(admin, 'bukti-selisih');
    expect((await admin.request.post('/__fixtures/counts/admin', { data: { company: run.company, upload: true } })).ok()).toBe(true);
    await admin.goto('/gudang/selisih-temuan');
    const row = admin.locator('[data-slot="case-row"]').filter({ hasText: run.name }).first();
    const share = row.locator('[data-slot="case-lot"]').filter({ hasText: run.lot }).first().getByLabel(/Jumlah dari/);
    await share.fill('1');
    await row.getByRole('button', { name: 'Tambah Bukti' }).click();
    // Pool evidence has no company: an issuer of this run's own, as Chromium and WebKit share one database.
    await register(admin, { name: 'catatan-gudang.pdf', mimeType: 'application/pdf', buffer: uniquePdf() }, `Gudang Uji ${String(Date.now())}`, { pool: true });

    await expect(admin).toHaveURL(/\/gudang\/selisih-temuan/);
    await expect(share).toHaveValue('1');
    await expect(row.getByLabel('Bukti', { exact: true }).locator('option:checked')).toHaveText('Bukti yang baru diunggah');
    await context.close();
});
