import { expect as base, test } from '@playwright/test';

import { otherCashRun } from '../other-cash-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-054; IP-08; IP-09; DESIGN_SYSTEM §15): "Catat Pengeluaran Kas" names its fields in a labelled sheet and asks its confirmation in a named dialog, "Kembali" closing it with nothing sent (NV-02).

test.describe.configure({ timeout: 240_000 });

test('names the disbursement form\'s fields and closes its confirmation with nothing sent', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await otherCashRun(page, `kas-lain-a11y-${String(Date.now())}`);

    await page.goto(`/keuangan/kas-lain?perusahaan=${run.company}`);
    await page.getByRole('button', { name: 'Catat Pengeluaran Kas', exact: true }).click();
    const sheet = page.getByRole('dialog');
    await sheet.getByRole('combobox', { name: 'Cabang', exact: true }).selectOption({ value: run.company });
    await expect(sheet.getByRole('combobox', { name: 'Jenis', exact: true })).toBeVisible();
    await expect(sheet.getByLabel('Jumlah')).toBeVisible();
    await expect(sheet.getByRole('combobox', { name: 'Rekening asal', exact: true })).toBeVisible();
    await expect(sheet.getByRole('combobox', { name: 'Bukti', exact: true })).toBeVisible();
    await expect(sheet).toContainText('Kas Keluar');
    await sheet.getByLabel('Jumlah').fill('1000');
    await sheet.getByRole('combobox', { name: 'Rekening asal', exact: true }).selectOption({ index: 1 });
    await sheet.getByRole('combobox', { name: 'Pembelian', exact: true }).selectOption({ value: run.purchase });
    await sheet.getByRole('combobox', { name: 'Bukti', exact: true }).selectOption({ value: run.transfer_proof });
    await sheet.getByRole('button', { name: 'Catat Pengeluaran Kas', exact: true }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText('Kas Keluar');
    await confirm.getByRole('button', { name: 'Kembali', exact: true }).click();
    await expect(confirm).toHaveCount(0);
    await expect(page.locator('#konten')).not.toContainText('Pengeluaran kas dicatat.');
});
