import { expect, test } from '@playwright/test';

import { confirmationRun } from '../confirmation-fixtures';

// TL-09 (TASK-034; J-PRJ-02's no-quotation path; J-FUL-01; J-PRJ-04's "Batalkan Sisa"; criteria 1, 5–8): "Konfirmasi
// Proyek" on PG-011 with the client's registered SPK after its confirmation, the lines then shown by mode with their
// progress; "Ubah Cara Pemenuhan" moving the goods line's unstarted remainder to *Kirim langsung*; "Batalkan Sisa" with
// its reason on the new line, its remainder then zero. Nothing implies that the confirmation reserved stock or recorded money.

test('confirms a project without a quotation and plans its lines on PG-011 (J-PRJ-02; J-FUL-01; criteria 1, 5, 7)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await confirmationRun(page, `konfirmasi-${String(Date.now())}`);
    const content = page.locator('#konten');

    await page.goto(`/proyek/${run.project}`);
    await expect(page.getByRole('heading', { name: 'Item dan pemenuhan' })).toBeVisible();
    await page.getByRole('button', { name: 'Konfirmasi Proyek' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('tidak memesan stok');
    await dialog.getByRole('button', { name: 'Konfirmasi Proyek' }).click();
    await expect(content.getByText('Proyek dikonfirmasi. Baris dan harganya sudah dikunci.')).toBeVisible();
    await expect(content).toContainText('Dikonfirmasi tanpa penawaran');

    const lines = page.getByRole('list', { name: 'Baris terkonfirmasi' });
    await expect(lines.getByRole('listitem')).toHaveCount(2);
    await expect(lines.locator('[data-line="1"]')).toContainText('Dari gudang');
    await expect(lines.locator('[data-line="2"]')).toContainText('Jasa');

    await lines.locator('[data-line="1"]').getByRole('button', { name: 'Ubah Cara Pemenuhan' }).click();
    const replan = page.getByRole('alertdialog');
    await replan.getByRole('textbox').last().fill('Pemasok kirim langsung ke sekolah');
    await replan.getByRole('button', { name: 'Ubah Cara Pemenuhan' }).click();
    await expect(content.getByText('Cara pemenuhan diubah untuk sisa baris.')).toBeVisible();
    await expect(lines.getByRole('listitem')).toHaveCount(3);
    await expect(lines.locator('[data-line="3"]')).toContainText('Kirim langsung');
    await expect(lines.locator('[data-line="3"]')).toContainText('dipecah dari baris 1');
    await expect(content).not.toContainText(/Reservasi dibuat|Pembayaran dicatat/);
});

test('cancels a line\'s remainder with its reason (J-PRJ-04; SF-CANCEL-LINE; criterion 6)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await confirmationRun(page, `batal-sisa-${String(Date.now())}`, { confirmed: true });
    const content = page.locator('#konten');

    await page.goto(`/proyek/${run.project}`);
    const line = page.getByRole('list', { name: 'Baris terkonfirmasi' }).locator('[data-line="2"]');
    await line.getByRole('button', { name: 'Batalkan Sisa' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('stok bebas');
    await dialog.getByRole('textbox').last().fill('Klien membatalkan pelatihan');
    await dialog.getByRole('button', { name: 'Batalkan Sisa' }).click();
    await expect(content.getByText('Sisa baris dibatalkan.')).toBeVisible();
    await expect(line).not.toContainText('Batalkan Sisa');
});
