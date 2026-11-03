import { expect as base, test } from '@playwright/test';

import { dropshipRun } from '../dropship-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-046; IP-08; DESIGN_SYSTEM §15): PG-032's fields by their names; "Konfirmasi Kirim Langsung" asks its confirmation in a named dialog, "Kembali" closing it with the input kept (NV-02).

test.describe.configure({ timeout: 240_000 });

test('names PG-032\'s fields and asks the confirmation in its dialog, the input kept', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await dropshipRun(page, `langsung-a11y-${String(Date.now())}`);

    await page.goto(`/proyek/${run.project}/kirim-langsung`);
    const form = page.locator('[data-slot="dropship-form"]');
    await expect(form.getByLabel('Pembelian')).toBeVisible();
    await form.getByLabel('Nomor pengiriman pemasok').fill('SJ-PMS-A11Y');
    await page.locator('[data-slot="dropship-entry"]').first().getByLabel(/^Jumlah dikirim/).fill('1');
    await form.getByLabel('Bukti dari pemasok').selectOption({ value: run.proof });
    await form.getByRole('button', { name: 'Konfirmasi Kirim Langsung', exact: true }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('stok gudang tidak berubah');
    await dialog.getByRole('button', { name: 'Kembali' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(form.getByLabel('Nomor pengiriman pemasok')).toHaveValue('SJ-PMS-A11Y');
});
