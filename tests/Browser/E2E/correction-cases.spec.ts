import { expect, test } from '@playwright/test';

import { correctionCases } from '../case-fixtures';

// TL-09 (TASK-031; ROUTE-040, ROUTE-271; SF-CASE; criteria 2, 4): PG-040 lists the run's cases — a closable sales
// return and a payment correction still carrying an unresolved deduction —; "Tutup Kasus" is offered only for the
// first and closes it with its outcome; the second names its residual and offers no closure.

test('closes a case without residual and keeps one with a residual open (J-DOC; SF-CASE; C-46)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const { company } = await correctionCases(page, `kasus-${String(Date.now())}`);
    const content = page.locator('#konten');

    await page.goto(`/dokumen/kasus?perusahaan=${company}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Kasus Koreksi');
    const closable = content.getByRole('region', { name: 'Retur penjualan' });
    const owing = content.getByRole('region', { name: 'Koreksi pembayaran' });
    await expect(closable).toContainText('Klien mengembalikan 2 unit rusak.');
    await expect(owing).toContainText('potongan yang belum terselesaikan');
    await expect(owing.getByRole('button', { name: 'Tutup Kasus' })).toHaveCount(0);

    await closable.getByRole('button', { name: 'Tutup Kasus' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Tutup kasus Retur penjualan?' });
    await dialog.getByRole('textbox').fill('Barang sudah kembali ke gudang dan dinilai.');
    await dialog.getByRole('button', { name: 'Tutup Kasus' }).click();
    await expect(dialog).toBeHidden();
    await expect(closable.getByText('Ditutup', { exact: true })).toBeVisible();
    await expect(closable).toContainText('Hasil: Barang sudah kembali ke gudang dan dinilai.');
    await expect(closable.getByRole('button', { name: 'Tutup Kasus' })).toHaveCount(0);
});
