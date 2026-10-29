import { expect, test } from '@playwright/test';

import { reservationRun } from '../reservation-fixtures';

// TL-09 (TASK-038; J-INV-02; CJ-01's reservation step; criteria 1, 2, 6): "Reservasi" from PG-011's *Item dan pemenuhan*
// after the confirmation — the dialog offering at most the line's reservable quantity and the availability, a refusal above
// the availability naming it (ST-06) —; then on PG-025 "Kurangi Reservasi" and "Lepas" with their reason from the row's
// actions, each answered on the page; the phone reading the rows without their actions (`[desk]`). The IP-20 cut block is
// tested with the first supply-reducing form (TASK-039, TASK-042); this Task's cut service is proven in TL-05.

test('reserves from Item dan pemenuhan, refused above the availability with it in words (J-INV-02; C-04; criteria 1, 2)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await reservationRun(page, `reservasi-${String(Date.now())}`, { stock: '8' });
    const content = page.locator('#konten');

    await page.goto(`/proyek/${run.project}`);
    const line = page.getByRole('list', { name: 'Baris terkonfirmasi' }).locator('[data-line="1"]');
    await line.getByRole('button', { name: 'Reservasi' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Paling banyak 8');
    await expect(dialog).toContainText('Tidak ada barang yang bergerak.');

    await dialog.getByRole('textbox').fill('9');
    await dialog.getByRole('button', { name: 'Reservasi' }).click();
    await expect(dialog).toContainText('Stok tersedia tidak cukup. Tersedia saat ini: 8.');

    await dialog.getByRole('textbox').fill('3');
    await dialog.getByRole('button', { name: 'Reservasi' }).click();
    await expect(content.getByText('Reservasi tersimpan.')).toBeVisible();
    await expect(line.locator('dd').nth(1)).toHaveText(/^3 /);
});

test('reduces and releases on PG-025 with a reason each (J-INV-02; AX-02; criterion 6)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await reservationRun(page, `reservasi-kurangi-${String(Date.now())}`, { stock: '8', reserved: '5' });
    const content = page.locator('#konten');

    await page.goto(`/gudang/reservasi?cabang=${run.company}`);
    await expect(page.getByRole('heading', { name: 'Reservasi' })).toBeVisible();
    const table = page.getByRole('table', { name: 'Reservasi' });
    await expect(table).toContainText(run.number);
    await expect(table).toContainText(run.goods);

    await table.getByRole('button', { name: 'Tindakan baris' }).click();
    await page.getByRole('menuitem', { name: 'Kurangi Reservasi' }).click();
    const reduce = page.getByRole('alertdialog');
    await expect(reduce).toContainText('Direservasi saat ini 5');
    await reduce.getByRole('textbox').first().fill('2');
    await reduce.getByRole('textbox').last().fill('Klien menunda sebagian');
    await reduce.getByRole('button', { name: 'Kurangi Reservasi' }).click();
    await expect(content.getByText('Reservasi dikurangi.')).toBeVisible();
    await expect(table.locator('tbody tr').first().locator('td').nth(5)).toHaveText(/^3 /);

    await table.getByRole('button', { name: 'Tindakan baris' }).click();
    await page.getByRole('menuitem', { name: 'Lepas' }).click();
    const release = page.getByRole('alertdialog');
    await release.getByRole('textbox').last().fill('Proyek ditunda');
    await release.getByRole('button', { name: 'Lepas' }).click();
    await expect(content.getByText('Reservasi dilepas.')).toBeVisible();
    await expect(content.getByText('Belum ada reservasi aktif di perusahaan ini.')).toBeVisible();

    await page.getByRole('tab', { name: 'Riwayat' }).click();
    await expect(page).toHaveURL(/tab=riwayat/);
    await expect(content).toContainText('Dilepas');
});

test('reads the reservations on the phone without their actions (J-INV-02 `[desk]`; NV-06)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await reservationRun(page, `reservasi-ponsel-${String(Date.now())}`, { stock: '8', reserved: '2' });

    await page.goto(`/gudang/reservasi?cabang=${run.company}`);
    const rows = page.getByRole('list', { name: 'Reservasi', exact: true });
    await expect(rows).toContainText(run.number);
    await expect(rows.getByRole('listitem').first()).toContainText(/(^|\s)2 /);
    await expect(page.getByRole('button', { name: 'Tindakan baris' })).toHaveCount(0);
});
