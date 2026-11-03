import { expect as base, type Locator, type Page, test } from '@playwright/test';

import { otherCashRun } from '../other-cash-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-054; J-FIN-07; CJ-14; INT-257–INT-260; NAV-049): at the desk, a purchase paid through "Catat Pengeluaran Kas" — the only Cash-Out —, a project expense citing its supplier bill through
// "Catat Beban", received cashback through "Catat Kas Masuk Lain", each answering in place; then the payment corrected by its contra, both visible.

test.describe.configure({ timeout: 360_000 });

async function record(page: Page, action: string, company: string, fill: (sheet: Locator) => Promise<void>): Promise<void> {
    await page.getByRole('button', { name: action, exact: true }).click();
    const sheet = page.getByRole('dialog');
    await sheet.getByRole('combobox', { name: 'Cabang', exact: true }).selectOption({ value: company });
    await fill(sheet);
    await sheet.getByRole('button', { name: action, exact: true }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: action, exact: true }).click();
}

test('J-FIN-07: pays a purchase, records a project expense and received cashback, then contras the payment (CJ-14; BR-FIN-11; L-43)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await otherCashRun(page, `kas-lain-${String(Date.now())}`);

    await page.goto(`/keuangan/kas-lain?perusahaan=${run.company}`);
    await expect(page.getByRole('heading', { name: 'Pengeluaran & Kas Lain', level: 1 })).toBeVisible();
    await expect(page.getByText('Pengeluaran kas adalah satu-satunya Kas Keluar')).toBeVisible();

    await record(page, 'Catat Pengeluaran Kas', run.company, async (sheet) => {
        await sheet.getByRole('combobox', { name: 'Jenis', exact: true }).selectOption({ value: 'PURCHASE_PAYMENT' });
        await sheet.getByLabel('Jumlah').fill('400000');
        await sheet.getByRole('combobox', { name: 'Rekening asal', exact: true }).selectOption({ index: 1 });
        await sheet.getByRole('combobox', { name: 'Pembelian', exact: true }).selectOption({ value: run.purchase });
        await sheet.getByRole('combobox', { name: 'Bukti', exact: true }).selectOption({ value: run.transfer_proof });
    });
    await expect(page.locator('#konten')).toContainText('Pengeluaran kas dicatat.');
    await expect(page.locator('#konten')).toContainText(run.purchase_number);

    await record(page, 'Catat Beban', run.company, async (sheet) => {
        await sheet.getByRole('textbox', { name: 'Kategori' }).fill('Sewa alat');
        await sheet.getByLabel('Jumlah').fill('60000');
        await sheet.getByRole('combobox', { name: 'Proyek penyebab', exact: true }).selectOption({ value: run.project });
        await sheet.getByRole('combobox', { name: 'Tagihan pemasok yang dikutip', exact: true }).selectOption({ value: run.bill });
    });
    await expect(page.locator('#konten')).toContainText('Beban dicatat.');
    await expect(page.locator('#konten')).toContainText('Sewa alat');

    await record(page, 'Catat Kas Masuk Lain', run.company, async (sheet) => {
        await sheet.getByRole('combobox', { name: 'Kategori', exact: true }).selectOption({ value: 'CASHBACK_INCOME' });
        await sheet.getByLabel('Jumlah').fill('15000');
        await sheet.getByRole('combobox', { name: 'Rekening penerima', exact: true }).selectOption({ index: 1 });
        await sheet.getByRole('combobox', { name: 'Bukti', exact: true }).selectOption({ value: run.transfer_proof });
    });
    await expect(page.locator('#konten')).toContainText('Kas masuk dicatat.');
    await expect(page.locator('#konten')).toContainText('Cashback diterima');

    await page.getByRole('tab', { name: 'Pengeluaran kas' }).click();
    await page.getByRole('button', { name: 'Tindakan baris' }).first().click();
    await page.getByRole('menuitem', { name: 'Batalkan Catatan (Kontra)' }).click();
    const contra = page.getByRole('alertdialog');
    await contra.getByLabel('Alasan').fill('Salah catat jumlah');
    await contra.getByRole('button', { name: 'Batalkan Catatan (Kontra)' }).click();
    await expect(page.locator('#konten')).toContainText('Pengeluaran kas dikontra.');
    await expect(page.locator('#konten')).toContainText('Dikontra');
});
