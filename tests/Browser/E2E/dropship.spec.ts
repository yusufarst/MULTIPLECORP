import { expect as base, test } from '@playwright/test';

import { dropshipRun } from '../dropship-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-046; J-FUL-03; CJ-01's drop-ship branch; INT-205; INT-206; NAV-032): from the journey's *Kirim langsung* step, the
// supplier's shipment confirmed with its reference and proof, the client's delivery recorded with it, back on PG-011; the
// confirmation then listed on PG-032 with its cost, part of it reversed with its reason.

test.describe.configure({ timeout: 360_000 });

test('J-FUL-03: confirms a direct shipment with its delivery from the journey, then reverses part of it (CJ-01; AX-08; L-40; CM-27)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await dropshipRun(page, `langsung-${String(Date.now())}`);

    await page.goto(`/proyek/${run.project}`);
    await page.getByRole('link', { name: 'Pesan dan konfirmasi kirim langsung' }).click();
    await page.waitForURL(new RegExp(`/proyek/${run.project}/kirim-langsung$`));
    await expect(page.locator('[data-slot="dropship-line"]').first()).toContainText('5');
    const form = page.locator('[data-slot="dropship-form"]');
    await form.getByLabel('Nomor pengiriman pemasok').fill('SJ-PMS-01');
    await page.locator('[data-slot="dropship-entry"]').first().getByLabel(/^Jumlah dikirim/).fill('3');
    await form.getByLabel('Bukti dari pemasok').selectOption({ value: run.proof });
    await form.getByRole('checkbox', { name: /Bukti terima klien sudah ada/ }).click();
    await form.getByLabel('Nama penerima').fill('Bu Sari');
    await form.getByLabel('Bukti terima klien', { exact: true }).selectOption({ value: run.proof });
    await form.getByRole('button', { name: 'Konfirmasi Kirim Langsung', exact: true }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByRole('button', { name: 'Konfirmasi Kirim Langsung', exact: true }).click();
    await page.waitForURL(new RegExp(`/proyek/${run.project}$`));
    await expect(page.locator('#konten')).toContainText('Kirim langsung terkonfirmasi.');

    await page.goto(`/proyek/${run.project}/kirim-langsung`);
    const confirmation = page.locator('[data-slot="dropship-confirmation"]').first();
    await expect(confirmation).toContainText('SJ-PMS-01');
    await expect(confirmation).toContainText('Diterima klien: Bu Sari');
    await expect(confirmation).toContainText('HPP');
});

test('J-FUL-03: reverses a confirmation recorded in error with its reason, in place (CM-27; IP-09; INT-206)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await dropshipRun(page, `langsung-balik-${String(Date.now())}`);

    await page.goto(`/proyek/${run.project}/kirim-langsung`);
    const form = page.locator('[data-slot="dropship-form"]');
    await form.getByLabel('Nomor pengiriman pemasok').fill('SJ-PMS-02');
    await page.locator('[data-slot="dropship-entry"]').first().getByLabel(/^Jumlah dikirim/).fill('2');
    await form.getByLabel('Bukti dari pemasok').selectOption({ value: run.proof });
    await form.getByRole('button', { name: 'Konfirmasi Kirim Langsung', exact: true }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Konfirmasi Kirim Langsung', exact: true }).click();
    await page.waitForURL(new RegExp(`/proyek/${run.project}$`));

    await page.goto(`/proyek/${run.project}/kirim-langsung`);
    await page.locator('[data-slot="dropship-confirmation"]').first().getByRole('button', { name: 'Balikkan Konfirmasi' }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByLabel(/^1\./).fill('1');
    await dialog.getByLabel('Alasan').fill('Salah catat jumlah');
    await dialog.getByRole('button', { name: 'Balikkan Konfirmasi', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Konfirmasi dibalikkan.');
    await expect(page.locator('[data-slot="dropship-confirmation"]').first()).toContainText('Konfirmasi dibalikkan · 1');
});
