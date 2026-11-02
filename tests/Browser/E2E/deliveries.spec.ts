import { expect as base, test } from '@playwright/test';

import { deliveryRun } from '../delivery-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-045; J-FUL-02; CJ-03; CJ-15; INT-201; NAV-030; NAV-031): a partial delivery recorded at the client's site on the phone with its proof, the rest at the desk, and the BAST's draft opened on PG-036 from the recorded facts.

test.describe.configure({ timeout: 360_000 });

test('J-FUL-02: records 2 of 3 shipped on the phone, the last one at the desk, and opens the BAST\'s draft (CJ-03; CJ-15; AX-09)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await deliveryRun(page, `kirim-${String(Date.now())}`);
    await page.setViewportSize({ width: 390, height: 844 });

    await page.goto('/gudang/pengiriman');
    await page.locator('[data-slot="delivery-row"]').filter({ hasText: run.project_number }).first().getByRole('link').first().click();
    await page.waitForURL(new RegExp(`/gudang/pengiriman/${run.shipment}`));
    const form = page.locator('[data-slot="delivery-form"]');
    await page.locator('[data-slot="delivery-line"]').first().getByLabel(/^Jumlah/).fill('2');
    await form.getByLabel('Nama penerima').fill('Bu Sari');
    await form.getByLabel('Bukti pengiriman').selectOption({ value: run.proof });
    await form.getByRole('button', { name: 'Catat Pengiriman', exact: true }).click();
    await page.waitForURL(/\/gudang\/pengiriman$/);
    await expect(page.locator('#konten')).toContainText('Pengiriman tercatat.');

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(`/gudang/pengiriman/${run.shipment}`);
    await expect(page.locator('[data-slot="delivery-line"]').first()).toContainText('Belum diterima');
    await page.locator('[data-slot="delivery-line"]').first().getByLabel(/^Jumlah/).fill('1');
    await page.locator('[data-slot="delivery-form"]').getByLabel('Nama penerima').fill('Pak Budi');
    await page.locator('[data-slot="delivery-form"]').getByLabel('Bukti pengiriman').selectOption({ value: run.proof });
    await page.locator('[data-slot="delivery-form"]').getByRole('button', { name: 'Catat Pengiriman', exact: true }).click();
    await page.waitForURL(/\/gudang\/pengiriman$/);

    await page.goto(`/gudang/pengiriman/${run.shipment}`);
    await expect(page.locator('[data-slot="delivery-form"]')).toHaveCount(0);
    await page.locator('[data-slot="delivery-record"]').first().getByRole('button', { name: 'Terbitkan BAST' }).click();
    await page.waitForURL(/\/dokumen\/[0-9a-f-]{36}/);
    await expect(page.locator('#konten')).toContainText('BAST');
});
