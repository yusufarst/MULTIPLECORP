import { expect as base, test } from '@playwright/test';

import { caseSettled, openReturn, returnRun } from '../return-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-044; J-PUR-03; INT-176; INT-177; INT-271; NAV-028): a unit returned to the supplier on the phone, its unrecovered share recorded and the case closed on the desktop.

test.describe.configure({ timeout: 360_000 });

test('J-PUR-03: returns a unit on the phone, records its unrecovered share and closes the case on the desktop (AX-11; NX-16; C-46)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await returnRun(page, `retur-${String(Date.now())}`);
    await page.setViewportSize({ width: 390, height: 844 });

    await openReturn(page, run);
    await page.locator('[data-slot="return-place"]').filter({ hasText: 'Layak pakai' }).first().getByLabel(/Jumlah dari/).fill('1');
    await page.locator('[data-slot="return-form"]').getByRole('button', { name: 'Retur ke Pemasok', exact: true }).click();
    await page.getByRole('alertdialog').getByLabel('Alasan').fill('Rusak dari pemasok');
    const returned = caseSettled(page);
    await page.getByRole('alertdialog').getByRole('button', { name: 'Retur ke Pemasok', exact: true }).click();
    await page.waitForURL(/kasus=/);
    await returned;
    await expect(page.locator('#konten')).toContainText('Retur ke pemasok tercatat.');
    await expect(page.locator('[data-slot="return-open"]')).toBeVisible();

    await page.setViewportSize({ width: 1280, height: 900 });
    const outcome = page.locator('[data-slot="return-outcome"]');
    await outcome.getByLabel('Hasil', { exact: true }).selectOption('UNRECOVERED');
    // Indonesian entry: a decimal comma, the point grouping thousands.
    await outcome.getByLabel('Nilai', { exact: true }).fill(run.unit_cost.replace('.', ','));
    await outcome.getByRole('button', { name: 'Catat Hasil' }).click();
    await page.getByRole('alertdialog').getByLabel('Alasan').fill('Pemasok menolak mengganti');
    const recorded = caseSettled(page);
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Hasil' }).click();
    await recorded;
    await expect(page.locator('#konten')).toContainText('Hasil retur tercatat.');
    await expect(page.locator('[data-slot="return-balanced"]')).toBeVisible();

    await page.locator('[data-slot="return-case"]').getByRole('button', { name: 'Tutup Kasus' }).click();
    await page.getByRole('alertdialog').getByLabel('Alasan').fill('Retur selesai');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Tutup Kasus' }).click();
    await expect(page.locator('[data-slot="return-case"]')).toContainText('Ditutup');
    await expect(page.locator('[data-slot="return-case"]').getByRole('button', { name: 'Tutup Kasus' })).toHaveCount(0);
});
