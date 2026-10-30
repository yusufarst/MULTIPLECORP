import { expect, test } from '@playwright/test';

import { reservationRun } from '../reservation-fixtures';

// TL-11 (TASK-038; NAV-025 `[desk]`; J-INV-02; PT-05; PT-06; PT-16): PG-025 — its table on the desktop, its dense rows on
// the phone — and PG-011's "Reservasi" dialog without a sideways scroll at every band edge.

for (const width of [320, 390, 768, 1280]) {
    test(`shows PG-025 and the reservation dialog without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await reservationRun(page, `reservasi-lebar-${String(width)}-${String(Date.now())}`, { reserved: '2' });

        await page.goto(`/gudang/reservasi?cabang=${run.company}`);
        await expect(page.getByRole('heading', { name: 'Reservasi', exact: true })).toBeVisible();
        await expect(page.locator('#konten')).toContainText(run.number);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.goto(`/proyek/${run.project}`);
        await page.getByRole('list', { name: 'Baris terkonfirmasi' }).locator('[data-line="1"]').getByRole('button', { name: 'Reservasi', exact: true }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
