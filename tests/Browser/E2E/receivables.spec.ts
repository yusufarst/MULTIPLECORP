import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-050; J-FIN-02; J-FIN-05; CJ-04's billing; CJ-14's receivables and ageing): "Tagihkan" with its dates from PG-043 and from PG-044's row, the receivable then active with a subtotal per company; the billing correction with the old dates visible; the hold, what the page says of it, and its clearing;
// the Owner's write-off with the client's credit to apply first or a reason, its history; money recovered applied and the write-off lifted in one action; DOC-13 from the invoice.

test.describe.configure({ timeout: 240_000 });

test('bills an invoice from PG-043 with its dates, the answer saying what became active, PG-044 listing it with its company\'s subtotal (J-FIN-02; AC-10; INT-243)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `piutang-${String(Date.now())}`, { stage: 'issued' });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page.getByTestId('billing')).toContainText('Belum ditagihkan');
    await page.getByRole('button', { name: 'Tagihkan' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Rp1.000.000 menjadi piutang aktif');
    await dialog.getByRole('textbox', { name: /^Tanggal jatuh tempo/ }).fill('31/12/2099');
    await dialog.getByRole('button', { name: 'Tagihkan' }).click();

    await expect(page.locator('#konten').getByText('Invoice ditagihkan. Sisa tagihannya kini piutang aktif.')).toBeVisible();
    await expect(page.getByTestId('billing')).toContainText('31 Desember 2099');
    await expect(page.locator('#konten')).toContainText('Ditagihkan');
    await expect(page.getByRole('button', { name: 'Tagihkan' })).toHaveCount(0);

    await page.goto(`/keuangan/piutang?perusahaan=${run.company}`);
    await expect(page.getByTestId('subtotals')).toContainText('Rp1.000.000');
    await expect(page.getByTestId('subtotals')).toContainText(run.code);
    await expect(page.getByRole('table', { name: 'Piutang' })).toContainText('Rp1.000.000');
});

test('bills an unbilled row from PG-044, returning to its tab (J-FIN-02; IP-18)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `piutang-baris-${String(Date.now())}`, { stage: 'issued' });

    await page.goto(`/keuangan/piutang?perusahaan=${run.company}&tab=belum`);
    await expect(page.getByRole('tab', { name: 'Belum Ditagihkan' })).toHaveAttribute('aria-selected', 'true');
    await page.getByRole('button', { name: 'Tindakan baris' }).click();
    await page.getByRole('menuitem', { name: 'Tagihkan' }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByRole('textbox', { name: /^Tanggal jatuh tempo/ }).fill('31/12/2099');
    await dialog.getByRole('button', { name: 'Tagihkan' }).click();

    await expect(page).toHaveURL(/tab=belum/);
    await expect(page.locator('#konten')).toContainText('Tidak ada invoice yang menunggu ditagihkan.');
    await page.getByRole('tab', { name: 'Aktif' }).click();
    await expect(page).toHaveURL(/tab=aktif/);
    await expect(page.getByRole('table', { name: 'Piutang' })).toContainText('Rp1.000.000');
});

test('corrects a billing with its evidence and reason, the old dates staying visible and the age following (CM-25; INT-244)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `piutang-koreksi-${String(Date.now())}`, { stage: 'issued', billed: true });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Koreksi Penagihan' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Tanggal lama tetap terlihat');
    // The filled field shows its long date until focused, then its digits to edit.
    const due = dialog.getByRole('textbox', { name: /^Tanggal jatuh tempo/ });
    await due.focus();
    await due.fill('30/11/2099');
    await dialog.getByRole('combobox').selectOption({ index: 1 });
    await dialog.getByRole('textbox').last().fill('Jatuh tempo salah catat');
    await dialog.getByRole('button', { name: 'Koreksi Penagihan' }).click();

    await expect(page.locator('#konten').getByText('Penagihan dikoreksi.')).toBeVisible();
    await expect(page.getByTestId('billing')).toContainText('30 November 2099');
    await expect(page.getByTestId('billing')).toContainText('Tanggal sebelumnya');
    await expect(page.getByTestId('billing')).toContainText('31 Desember 2099');
    await expect(page.getByTestId('billing')).toContainText('Jatuh tempo salah catat');
});

test('marks a dispute with its reason, saying what it does, the receivable still listed, and clears it (J-FIN-05; BR-FIN-08; INT-246)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `piutang-sengketa-${String(Date.now())}`, { stage: 'issued', billed: true });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Tandai Sengketa' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('proyek tidak bisa diselesaikan secara normal');
    await dialog.getByRole('textbox').last().fill('Klien membantah jumlahnya');
    await dialog.getByRole('button', { name: 'Tandai Sengketa' }).click();

    await expect(page.getByTestId('hold')).toContainText('Klien membantah jumlahnya');
    await expect(page.getByTestId('hold')).toContainText('Piutang tetap aktif dan umurnya terus berjalan');
    await page.goto(`/keuangan/piutang?perusahaan=${run.company}&tab=sengketa`);
    await expect(page.getByRole('table', { name: 'Piutang' })).toContainText('Sengketa');

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Cabut Sengketa' }).click();
    const clearing = page.getByRole('alertdialog');
    await clearing.getByRole('textbox').last().fill('Klien sudah setuju');
    await clearing.getByRole('button', { name: 'Cabut Sengketa' }).click();
    await expect(page.locator('#konten').getByText('Tanda sengketa dicabut.')).toBeVisible();
    await expect(page.getByTestId('hold')).toHaveCount(0);
});

test('lets the Owner write off, the client\'s credit first or a reason, the amount staying as history (J-FIN-05; L-17; DIR-019; INT-247)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `piutang-hapus-${String(Date.now())}`, { stage: 'issued', billed: true, credit: '50000' });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Hapuskan Piutang' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Tidak ada uang masuk dan tidak ada laba');
    await expect(dialog.getByTestId('credit')).toContainText('Rp50.000');
    await dialog.getByRole('textbox', { name: /^Jumlah dihapuskan/ }).fill('300000');
    await dialog.getByRole('textbox').last().fill('Klien tidak dapat ditagih');
    await dialog.getByRole('button', { name: 'Hapuskan Piutang' }).click();
    await expect(dialog).toContainText('masih punya kredit Rp50.000');

    await dialog.getByRole('textbox', { name: /^Alasan kredit tidak diterapkan/ }).fill('Kredit itu untuk proyek lain');
    await dialog.getByRole('button', { name: 'Hapuskan Piutang' }).click();

    await expect(page.locator('#konten').getByText('Piutang dihapuskan sebesar jumlah itu.')).toBeVisible();
    await expect(page.getByTestId('writeoffs')).toContainText('Rp300.000');
    await expect(page.getByTestId('writeoffs')).toContainText('Klien tidak dapat ditagih');
    await expect(page.getByTestId('position')).toContainText('Rp700.000');
});

test('applies money recovered after a write-off and lifts it in one action, the original staying (J-FIN-05; L-17; AX-24; INT-248)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `piutang-cabut-${String(Date.now())}`, { stage: 'issued', billed: true, writtenOff: '500000', recovery: '200000' });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page.getByTestId('recoveries')).toContainText('Rp200.000');
    await page.getByRole('button', { name: 'Terapkan dan Cabut Penghapusan' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('dalam satu tindakan');
    await dialog.getByRole('textbox').last().fill('Klien membayar kembali');
    await dialog.getByRole('button', { name: 'Terapkan dan Cabut Penghapusan' }).click();

    await expect(page.locator('#konten').getByText('Pembayaran diterapkan dan penghapusan dicabut.')).toBeVisible();
    await expect(page.getByTestId('writeoffs')).toContainText('Rp500.000');
    await expect(page.getByTestId('writeoffs')).toContainText('Masih berlaku Rp300.000');
    await expect(page.getByTestId('recoveries')).toHaveCount(0);
});

test('prepares the payment request from the invoice, on PG-036 with the billing data (DOC-13; BR-DOC-04; J-FIN-02)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `piutang-surat-${String(Date.now())}`, { stage: 'issued', billed: true });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await page.getByRole('button', { name: 'Buat Surat Permintaan Pembayaran' }).click();

    await expect(page).toHaveURL(/\/dokumen\//);
    await expect(page.locator('#konten')).toContainText('Surat Permintaan Pembayaran');
    await expect(page.locator('#konten')).toContainText('Rp1.000.000');
    await expect(page.locator('#konten')).toContainText('Jatuh Tempo');
});

test('answers a cursor that reads nothing with MSG-26 and the offer to load from the start, never a silent first page (IP-18; NV-05; INT-008)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `piutang-kursor-${String(Date.now())}`, { stage: 'issued', billed: true });

    await page.goto(`/keuangan/piutang?perusahaan=${run.company}&setelah=bukan-kursor`);
    await expect(page.locator('#konten')).toContainText('Daftar tidak dapat dilanjutkan.');
    await expect(page.getByRole('table', { name: 'Piutang' })).toHaveCount(0);
    await page.locator('[data-slot="list-continuation"]').getByRole('button').click();

    await expect(page.getByRole('table', { name: 'Piutang' })).toContainText('Rp1.000.000');
    await expect(page).not.toHaveURL(/setelah=/);
});

