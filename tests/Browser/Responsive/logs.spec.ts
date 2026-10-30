import { expect, test } from '@playwright/test';

import { logsRun } from '../logs-fixtures';

// TL-11 (TASK-061; NAV-063; NAV-064 `[desk]`; PT-05; PT-06): PG-063 and PG-064 — the table on the desktop, the dense rows
// on the phone — without a sideways scroll at every band edge.

for (const width of [320, 390, 768, 1280]) {
    test(`shows PG-063 and PG-064 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await logsRun(page, `log-lebar-${String(width)}-${String(Date.now())}`);

        await page.goto(`/aktivitas/riwayat?perusahaan=${run.code}`);
        await expect(page.getByRole('heading', { name: 'Riwayat Aktivitas' })).toBeVisible();
        await expect(page.locator('#konten')).toContainText('Membuat proyek');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.goto(`/aktivitas/log-keamanan?peristiwa=${encodeURIComponent(run.notice)}&asal=beranda`);
        await expect(page.getByRole('heading', { name: 'Log Keamanan' })).toBeVisible();
        await expect(page.locator('#konten')).toContainText('Kejanggalan akses');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
