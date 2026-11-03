import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-056; J-PRJ-04; CJ-17's cancellation step; INT-163): at the desk, "Batalkan Proyek" from PG-011's *Tindakan Lain*, its dialog listing the companion corrections as
// links and taking the reason, the project then CANCELLED with its record.

test.describe.configure({ timeout: 360_000 });

test('J-PRJ-04: cancels a project with its reason from PG-011, the companion corrections listed (AX-27; IP-19)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `batal-${String(Date.now())}`, { stage: 'issued' });

    await page.goto(`/proyek/${run.project}`);
    await page.getByRole('button', { name: 'Tindakan Lain' }).first().click();
    await page.getByRole('menuitem', { name: 'Batalkan Proyek' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog.locator('[data-slot="cancel-companions"]')).toContainText('Batalkan atau revisi invoice');
    await expect(dialog).toContainText('stok bebas');
    await dialog.getByLabel('Alasan').fill('Klien membatalkan kontrak');
    await dialog.getByRole('button', { name: 'Batalkan Proyek' }).click();
    await expect(page.locator('#konten')).toContainText('Proyek dibatalkan.');
    await expect(page.locator('[data-slot="cancelled-record"]')).toContainText('Klien membatalkan kontrak');
});
