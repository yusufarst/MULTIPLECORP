import { expect as base, test } from '@playwright/test';

import { clientReturnRun } from '../client-return-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-048; IP-08; IP-09; DESIGN_SYSTEM §15): PG-034's fields by their names; "Catat Retur dari Klien" asks its reason in a named dialog, "Kembali" closing it with the input kept (NV-02).

test.describe.configure({ timeout: 240_000 });

test('names PG-034\'s fields and asks the reason in its dialog, the input kept', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await clientReturnRun(page, `retur-a11y-${String(Date.now())}`);

    await page.goto(`/proyek/${run.project_id}/retur`);
    const form = page.locator('[data-slot="return-form"]');
    await expect(form.getByLabel('Baris pengiriman')).toBeVisible();
    await expect(form.getByLabel('Kondisi saat diterima')).toBeVisible();
    await page.locator('[data-slot="return-unit"]').first().getByLabel(/^Jumlah kembali/).fill('1');
    await form.getByLabel('Rak tujuan').selectOption({ value: run.rack_handle });
    await form.getByLabel('Bukti retur').selectOption({ value: run.return_proof });
    await form.getByRole('button', { name: 'Catat Retur dari Klien', exact: true }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Kasus retur dibuka');
    await dialog.getByRole('button', { name: 'Kembali' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page.locator('[data-slot="return-unit"]').first().getByLabel(/^Jumlah kembali/)).toHaveValue('1');
});
