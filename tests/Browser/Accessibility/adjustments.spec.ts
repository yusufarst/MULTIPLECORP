import { expect as base, test } from '@playwright/test';

import { adjustmentRun } from '../adjustment-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-042; IP-09; IP-20): the reason reachable in its step and announced by its name, the units by their labels, the relation marker read as text.

test.describe.configure({ timeout: 240_000 });

test('names the units, the proof and the reason on PG-027', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await adjustmentRun(page, `sesuai-a11y-${String(Date.now())}`);

    await page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=kondisi`);
    await expect(page.locator('[data-slot="adjustment-lot"]').filter({ hasText: 'perusahaan lain' }).first()).toBeVisible();
    const row = page.locator('[data-slot="adjustment-lot"]').filter({ hasText: run.lot }).first();
    await row.getByLabel(/Jumlah dari/).fill('1');
    await page.getByLabel('Bukti', { exact: true }).selectOption({ index: 1 });
    await page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Ubah Kondisi' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog.getByLabel('Alasan')).toBeFocused();
});
