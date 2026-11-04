import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-056; IP-09; DESIGN_SYSTEM §15): "Batalkan Proyek" asks its reason in a named dialog, its label read, "Kembali" closing it with nothing sent and the focus back on the page.

test.describe.configure({ timeout: 240_000 });

test('names the cancellation dialog\'s reason and closes it with nothing sent', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `batal-a11y-${String(Date.now())}`);

    await page.goto(`/proyek/${run.project}`);
    await page.getByRole('button', { name: 'Tindakan Lain' }).first().click();
    await page.getByRole('menuitem', { name: 'Batalkan Proyek' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Batalkan Proyek' });
    await expect(dialog.getByLabel('Alasan')).toBeVisible();
    await dialog.getByRole('button', { name: 'Kembali', exact: true }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page.locator('[data-slot="cancelled-record"]')).toHaveCount(0);
});
