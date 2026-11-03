import { expect as base, test } from '@playwright/test';

import { clientReturnRun } from '../client-return-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-048; J-FUL-05; CJ-06; INT-210; INT-211; INT-271; NAV-034): a delivered unit taken back from PG-030's delivered record on the phone with its proof and reason, its case and open steps at the desk, the return reversed as recorded in error and the case closed, each answering on PG-034.

test.describe.configure({ timeout: 360_000 });

test('J-FUL-05: takes one delivered unit back on the phone, then reverses it and closes its case at the desk (CJ-06; AX-10; AX-36)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await clientReturnRun(page, `retur-${String(Date.now())}`);
    await page.setViewportSize({ width: 390, height: 844 });

    await page.goto('/gudang/pengiriman?tab=tercatat');
    await page.locator('[data-slot="delivery-row"]').filter({ hasText: run.project_number }).first().getByRole('link', { name: 'Retur dari Klien' }).click();
    await page.waitForURL(new RegExp(`/proyek/${run.project_id}/retur$`));
    const form = page.locator('[data-slot="return-form"]');
    await page.locator('[data-slot="return-unit"]').first().getByLabel(/^Jumlah kembali/).fill('1');
    await form.getByLabel('Rak tujuan').selectOption({ value: run.rack_handle });
    await form.getByLabel('Bukti retur').selectOption({ value: run.return_proof });
    await form.getByRole('button', { name: 'Catat Retur dari Klien', exact: true }).click();
    const recording = page.getByRole('alertdialog');
    await recording.getByLabel('Alasan').fill('Klien menolak satu unit');
    await recording.getByRole('button', { name: 'Catat Retur dari Klien', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Retur dari klien dicatat; kasusnya dibuka.');
    await expect(page.locator('[data-slot="return-line"]').first()).toContainText('kembali 1');

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.reload();
    const returnCase = page.locator('[data-slot="return-case"]').first();
    await expect(returnCase).toContainText('Terbuka');
    await expect(returnCase).toContainText('Kebutuhan proyek disesuaikan');
    await returnCase.getByRole('button', { name: 'Balikkan (Tercatat Keliru)' }).click();
    const reversing = page.getByRole('alertdialog');
    await reversing.getByLabel('Alasan').fill('Unit tercatat keliru');
    await reversing.getByRole('button', { name: 'Balikkan (Tercatat Keliru)', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Retur dibalik.');
    await expect(page.locator('[data-slot="return-line"]').first()).toContainText('kembali 0');

    await page.locator('[data-slot="return-case"]').first().getByRole('button', { name: 'Tutup Kasus' }).click();
    const closing = page.getByRole('alertdialog');
    await closing.getByLabel('Alasan').fill('Retur batal, unit tetap di klien');
    await closing.getByRole('button', { name: 'Tutup Kasus', exact: true }).click();
    await page.waitForURL(new RegExp(`/proyek/${run.project_id}/retur$`));
    await expect(page.locator('[data-slot="return-case"]').first()).toContainText('Ditutup');
});
