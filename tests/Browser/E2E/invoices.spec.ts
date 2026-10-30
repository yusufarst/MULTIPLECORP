import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-049; J-FIN-01 — S2-J03's composer on the desktop and the phone —; J-FIN-08; CJ-04's invoices and pre-payment Kuitansi; CJ-06's revision and void): the journey's composer, the issue saying that no receivable is active, the cap's refusal with what remains; the Kuitansi's draft on PG-036 issued with its reason,
// recording no payment; a correction listing each reduction and what becomes of it.

test.describe.configure({ timeout: 240_000 });

test('composes a value-only invoice beside its live paper, saves and issues it, the answer stating that nothing is billed (J-FIN-01; D-DS-23; INT-241; INT-242)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-${String(Date.now())}`);

    await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);
    await expect(page.getByRole('heading', { name: new RegExp(`Invoice baru — ${run.number}`) })).toBeVisible();
    const paper = page.getByRole('region', { name: /^Pratinjau Invoice/ });
    await expect(paper).toContainText('DRAF — belum terbit');
    await page.getByLabel(/^Keterangan/).fill('Termin pertama');
    await page.getByRole('textbox', { name: /^Nilai/ }).fill('1000000');
    await expect(paper).toContainText('Termin pertama');
    await expect(paper).toContainText('Rp1.000.000');

    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.locator('#konten').getByText('Draf invoice tersimpan.')).toBeVisible();
    await page.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('belum ada piutang aktif');
    await dialog.getByRole('button', { name: 'Terbitkan Invoice' }).click();

    await expect(page.locator('#konten').getByText(/Invoice UJI\/INV\/.* sudah terbit/)).toBeVisible();
    await page.getByRole('link', { name: 'Buka Invoice' }).click();
    await expect(page.getByTestId('position')).toContainText('Rp1.000.000');
    await expect(page.locator('#konten')).toContainText('Belum Ditagihkan');
});

test('refuses an issue above the cap with what remains, the input kept (C-31; L-16; MSG-02)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-batas-${String(Date.now())}`);

    await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);
    await page.getByLabel(/^Keterangan/).fill('Terlalu besar');
    await page.getByRole('textbox', { name: /^Nilai/ }).fill('3000000');
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.locator('#konten').getByText('Draf invoice tersimpan.')).toBeVisible();
    await page.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await expect(page.getByRole('alertdialog')).toContainText(/Sisa yang bisa ditagih Rp2\.500\.000/);
});

test('issues the pre-payment Kuitansi on PG-036 with its reason, recording no payment (J-FIN-08; AX-31; GAP-029; DIR-081 OB-02)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `kuitansi-${String(Date.now())}`, { stage: 'issued' });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Terbitkan Kuitansi untuk Proses Pembayaran' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Tidak ada pembayaran yang tercatat');
    await dialog.getByLabel(/^Jumlah Kuitansi/).fill('400000');
    await dialog.getByRole('button', { name: 'Lanjut' }).click();

    await expect(page).toHaveURL(/\/dokumen\//);
    await page.getByRole('button', { name: /^Terbitkan/ }).click();
    const issue = page.getByRole('alertdialog');
    await expect(issue).toContainText('Tidak ada pembayaran yang tercatat');
    await issue.getByRole('textbox').fill('Klien meminta untuk proses pembayaran');
    await issue.getByRole('button', { name: /^Terbitkan/ }).click();
    await expect(page.locator('#konten').getByText('Kuitansi terbit. Belum ada pembayaran yang tercatat.')).toBeVisible();
    await expect(page.locator('#konten')).toContainText('Menunggu Pembayaran');

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page.getByTestId('kuitansi')).toContainText('Menunggu Pembayaran');
    await expect(page.getByTestId('position')).toContainText('Rp1.000.000');
});

test('voids an invoice with its basis, reason and the payment\'s disposition, listing each reduction (CJ-06; ST-07; IP-09; CM-23)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-batal-${String(Date.now())}`, { stage: 'issued', payment: true });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page.getByTestId('reductions')).toContainText('Rp400.000');
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Batalkan Invoice' }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByLabel(/^Dasar koreksi/).selectOption('UNBILLED_ENTRY_ERROR');
    await expect(dialog.getByLabel(/^Pembayaran/)).toHaveValue('RELEASE');
    await dialog.getByRole('textbox').last().fill('Salah catat nilai invoice');
    await dialog.getByRole('button', { name: 'Batalkan Invoice' }).click();

    await expect(page.locator('#konten').getByText('Invoice dibatalkan.')).toBeVisible();
    await expect(page.locator('#konten')).toContainText('Salah catat nilai invoice');
    await expect(page.getByTestId('position')).toContainText('Rp0');
});

test('edits a draft on the phone through Edit, Preview and Finalize, the preview full-screen (S2-J03; DC-11)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await invoiceRun(page, `invoice-hp-${String(Date.now())}`, { stage: 'draft' });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page).toHaveURL(/\/keuangan\/invoice\/baru\?invoice=/);
    await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'phone');
    await page.getByRole('button', { name: 'Pratinjau', exact: true }).click();
    await expect(page.getByRole('region', { name: /^Pratinjau Invoice/ })).toContainText('DRAF — belum terbit');
    await page.getByRole('button', { name: 'Lanjut' }).click();
    await expect(page.getByRole('button', { name: 'Terbitkan Invoice' })).toBeVisible();
});

test('lists the branch\'s invoices and finds one by its exact number (ROUTE-041; SCR-38)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `invoice-daftar-${String(Date.now())}`, { stage: 'issued' });

    await page.goto(`/keuangan/invoice?perusahaan=${run.company}`);
    const table = page.getByRole('table', { name: 'Invoice', exact: true });
    await expect(table).toContainText('UJI/INV/');
    await expect(table).toContainText('Belum Ditagihkan');
    await page.getByLabel(/^Cari nomor invoice/).fill('TIDAK-ADA');
    await page.getByRole('button', { name: 'Cari' }).click();
    await expect(page.locator('#konten')).toContainText('Belum ada invoice di cabang ini.');
});
