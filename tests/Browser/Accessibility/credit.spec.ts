import { expect as base, test } from '@playwright/test';

import { creditRun } from '../credit-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-053; IP-08; IP-09; DESIGN_SYSTEM §15): "Kembalikan Dana" asks its fields and reason in a named dialog, "Kembali" closing it with nothing sent (NV-02).

test.describe.configure({ timeout: 240_000 });

test('names the refund dialog\'s fields and closes it with nothing sent', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await creditRun(page, `kredit-a11y-${String(Date.now())}`);

    await page.goto('/keuangan/kredit-pelanggan');
    const group = page.locator('[data-slot="credit-group"]').filter({ hasText: run.client_name });
    await group.locator('[data-slot="credit-source"]').first().getByRole('button', { name: 'Kembalikan Dana' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog.getByLabel('Jumlah')).toBeVisible();
    await expect(dialog.getByLabel('Rekening asal')).toBeVisible();
    await expect(dialog.getByLabel('Bukti transfer')).toBeVisible();
    await expect(dialog).toContainText('Kas Keluar');
    await dialog.getByRole('button', { name: 'Kembali', exact: true }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page.locator('[data-slot="credit-refund"]')).toHaveCount(0);
});
