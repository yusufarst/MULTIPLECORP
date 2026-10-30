import { expect, test } from '@playwright/test';

import { logsRun } from '../logs-fixtures';

// TL-09 (TASK-061; ROUTE-063; ROUTE-064; INT-020; criteria 2, 3, 5, 6): PG-063 by company and without a company, "Muat
// Lagi" adding the rest of the company's events, an event's before and after on demand; PG-064 by kind; a security
// notice's address opening its event above the list with "Kembali". *Beranda*'s notice itself is TASK-058's.

test('searches the activity of a company and of no company, continues and opens an event\'s change (PG-063; criteria 2, 6)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await logsRun(page, `riwayat-${String(Date.now())}`);
    const table = page.getByRole('table', { name: 'Riwayat Aktivitas', exact: true });

    await page.goto(`/aktivitas/riwayat?perusahaan=${run.code}`);
    await expect(page.getByRole('heading', { name: 'Riwayat Aktivitas' })).toBeVisible();
    await expect(table.locator('tbody tr')).toHaveCount(50);
    await expect(table).toContainText('Membuat proyek');
    await expect(table).toContainText(`Uji log ${run.tag} 55`);

    await expect(table.getByRole('cell', { name: `Uji log ${run.tag} 1`, exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Muat Lagi' }).click();
    await expect(table.locator('tbody tr')).toHaveCount(55);
    await expect(table.getByRole('cell', { name: `Uji log ${run.tag} 1`, exact: true })).toHaveCount(1);

    await table.locator('tbody tr').first().getByRole('button', { name: 'Tindakan baris' }).click();
    await page.getByRole('menuitem', { name: 'Lihat sebelum dan sesudah' }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Rincian perubahan');
    await expect(dialog).toContainText('Lama 55');
    await expect(dialog).toContainText('Baru 55');
    await page.keyboard.press('Escape');

    // The run's own event without a company, narrowed to its Owner so another spec's events never crowd it out.
    await page.goto(`/aktivitas/riwayat?perusahaan=tanpa&pelaku=${run.actor}`);
    await expect(table).toContainText(`Tanpa perusahaan ${run.tag}`);
});

test('filters the security log by kind and opens a notice\'s event with "Kembali" (PG-064; INT-020; criteria 3, 5)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await logsRun(page, `keamanan-${String(Date.now())}`);
    const table = page.getByRole('table', { name: 'Log Keamanan', exact: true });

    await page.goto('/aktivitas/log-keamanan?jenis=AUTHORIZATION_ANOMALY');
    await expect(table).toContainText('Kejanggalan akses');
    await expect(table).not.toContainText('Gagal masuk');

    await page.goto(`/aktivitas/log-keamanan?peristiwa=${encodeURIComponent(run.notice)}&asal=beranda`);
    const focus = page.getByRole('region', { name: 'Peristiwa dari pemberitahuan' });
    await expect(focus).toContainText('Kejanggalan akses');
    await expect(focus).toContainText(`UJI-${run.tag}`);
    await expect(page.getByRole('link', { name: 'Kembali' })).toBeVisible();
});
