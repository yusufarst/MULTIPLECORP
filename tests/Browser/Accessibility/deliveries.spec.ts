import { expect as base, test } from '@playwright/test';

import { deliveryRun } from '../delivery-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-045; IP-09; DESIGN_SYSTEM §15): PG-031's fields by their names; "Tutup Pengiriman" asks its reason in a named dialog, "Kembali" closing it.

test.describe.configure({ timeout: 240_000 });

test('names PG-031\'s fields and asks the closure\'s reason in its dialog', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await deliveryRun(page, `kirim-a11y-${String(Date.now())}`);

    await page.goto(`/gudang/pengiriman/${run.shipment}`);
    const form = page.locator('[data-slot="delivery-form"]');
    await expect(form.getByLabel('Nama penerima')).toBeVisible();
    await expect(form.getByLabel('Bukti pengiriman')).toBeVisible();
    await page.locator('[data-slot="delivery-line"]').first().getByRole('button', { name: 'Tutup Pengiriman' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog.getByLabel('Alasan')).toBeVisible();
    await expect(dialog.getByLabel('Jenis penutupan')).toBeVisible();
    await dialog.getByRole('button', { name: 'Kembali' }).click();
    await expect(dialog).toHaveCount(0);
});
