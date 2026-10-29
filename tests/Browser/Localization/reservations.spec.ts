import { expect, test } from '@playwright/test';

import { reservationRun } from '../reservation-fixtures';

// TL-10 (TASK-038; LOCALIZATION §3; DOD-16): PG-025 and PG-011's "Reservasi" in both languages — keys of the catalogue,
// never hardcoded copy; the product's own name stays as entered.

for (const [locale, texts] of [
    ['id', { page: 'Reservasi', tab: 'Perlu dilepas', demand: 'Perlu direservasi', history: 'Riwayat', active: 'Aktif', reserve: 'Reservasi', lines: 'Baris terkonfirmasi', title: 'Reservasi baris 1' }],
    ['en', { page: 'Reservations', tab: 'To be released', demand: 'To be reserved', history: 'History', active: 'Active', reserve: 'Reserve', lines: 'Confirmed lines', title: 'Reserve line 1' }],
] as const) {
    test(`shows PG-025 and PG-011's "Reservasi" in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await reservationRun(page, `reservasi-${locale}-${String(Date.now())}`, { reserved: '2', locale });

        await page.goto(`/gudang/reservasi?cabang=${run.company}&tab=aktif`);
        await expect(page.getByRole('heading', { name: texts.page, exact: true })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.tab })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.history })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.demand })).toBeVisible();
        const table = page.getByRole('table', { name: texts.page, exact: true });
        await expect(table).toContainText(run.goods);
        await expect(table).toContainText(texts.active);

        await page.goto(`/proyek/${run.project}`);
        await page.getByRole('list', { name: texts.lines }).locator('[data-line="1"]').getByRole('button', { name: texts.reserve, exact: true }).click();
        await expect(page.getByRole('alertdialog')).toContainText(texts.title);
    });
}
