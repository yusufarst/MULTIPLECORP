import { expect, test } from '@playwright/test';

import { quotationRun } from '../quotation-fixtures';

// TL-09 (TASK-033; J-QUO-01; J-PRJ-02's approval; CJ-01's quotation and approval steps; CJ-13; criteria 1, 3, 4, 5): the
// draft created from the project's lines on PG-014, its validity and price entered beside the live paper and saved,
// "Terbitkan Penawaran" after its confirmation giving the number, "Catat Jawaban Klien" recording the approval and
// landing on PG-011; the revision on PG-037; and on the phone the sent quotation's quick action recording a rejection,
// then "Buat Revisi". No control voids a quotation or implies a reservation or a payment.

test('drafts, issues and approves a quotation from PG-014, landing on PG-011 (J-QUO-01; J-PRJ-02; CJ-01; criteria 1, 3)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await quotationRun(page, `penawaran-${String(Date.now())}`);
    const content = page.locator('#konten');

    await page.goto(`/proyek/${run.project}/penawaran`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${run.number}`);
    await page.getByRole('button', { name: 'Buat Draf dari Baris Proyek' }).click();
    await expect(page.getByText('Draf penawaran dibuat dari baris proyek.')).toBeVisible();

    await page.getByLabel(/^Berlaku sampai/).fill('31/12/' + String(new Date().getFullYear() + 1));
    await page.getByRole('textbox', { name: /^Harga satuan/ }).first().fill('20000');
    await expect(page.getByRole('region', { name: /Pratinjau/ })).toContainText('Penawaran Harga');
    await expect(page.getByRole('region', { name: /Pratinjau/ })).toContainText('Rp20.000');
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.getByText('Draf penawaran tersimpan.')).toBeVisible();

    await page.getByRole('button', { name: 'Terbitkan Penawaran' }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText('Penawaran tidak memesan stok dan tidak mencatat uang.');
    await confirm.getByRole('button', { name: 'Terbitkan Penawaran' }).click();
    await expect(page.getByText('Penawaran terbit. Berkasnya sedang disiapkan.')).toBeVisible();
    await expect(content).toContainText(/UJI\/PNW\/\d{4}\/0001/);
    await expect(content).not.toContainText(/Batalkan|Reservasi|Pembayaran/);

    await page.getByRole('button', { name: 'Catat Jawaban Klien' }).first().click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Disetujui' }).click();
    await page.waitForURL(`**/proyek/${run.project}`);
    await expect(page.getByText('Persetujuan klien dicatat sebagai konfirmasi komersial.')).toBeVisible();

    await page.goto(`/dokumen/penawaran?perusahaan=${run.company}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Daftar Penawaran');
    const row = page.getByRole('row').filter({ hasText: run.number });
    await expect(row).toContainText('Disetujui Klien');
    await expect(row).toContainText(/UJI\/PNW\/\d{4}\/0001/);
});

test('records the client\'s rejection through the phone\'s quick action, then creates a revision (J-QUO-01; criterion 4)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await quotationRun(page, `penawaran-hp-${String(Date.now())}`, { stage: 'sent' });

    await page.goto(`/proyek/${run.project}/penawaran`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${run.number}`);
    await page.getByRole('button', { name: 'Catat Jawaban Klien' }).first().click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByRole('radio', { name: 'Klien menolak' }).check();
    await dialog.getByLabel(/^Catatan/).fill('Anggaran belum tersedia');
    await dialog.getByRole('button', { name: 'Catat Ditolak' }).click();
    await expect(page.getByText('Penolakan klien dicatat.')).toBeVisible();
    await expect(page.locator('#konten')).toContainText('Ditolak Klien');

    await page.getByRole('button', { name: 'Buat Revisi' }).first().click();
    await expect(page.getByText('Revisi baru dibuat sebagai draf.')).toBeVisible();
    await expect(page.getByRole('list', { name: 'Riwayat revisi' }).getByRole('listitem')).toHaveCount(2);
});
