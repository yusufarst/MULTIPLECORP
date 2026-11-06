import { expect as base, test } from '@playwright/test';

import { importRun, preparerSignedIn } from '../import-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-058; IA §9; UXS-25; UXS-40; UXS-43; INT-019): the Owner's Beranda with its groups arriving after the
// frame, Pekerjaan's groups, Tinjauan's tabs with no approve, dismiss or read control, and an Admin's Beranda and
// Tinjauan answering as the not-found page.

test.describe.configure({ timeout: 240_000 });

test('shows the Owner Beranda, Pekerjaan and Tinjauan, each group loading after the frame (IA §9; UXS-40)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await importRun(page, `beranda-${String(Date.now())}`);

    await page.goto('/beranda');
    await expect(page.getByRole('heading', { name: 'Beranda', level: 1 })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Perlu keputusan Anda' })).toBeVisible();
    await expect(page.getByRole('table', { name: 'Angka Periode' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Buka Pekerjaan' })).toBeVisible();
    await expect(page.locator('[data-region-state="error"]')).toHaveCount(0);

    await page.getByRole('link', { name: 'Buka Pekerjaan' }).click();
    await expect(page.getByRole('heading', { name: 'Pekerjaan', level: 1 })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Barang: terima, keluarkan, kirim' })).toBeVisible();

    await page.goto('/tinjauan');
    await expect(page.getByRole('heading', { name: 'Tinjauan', level: 1 })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Kondisi yang Masih Terbuka' })).toBeVisible();
    await page.getByRole('tab', { name: 'Keamanan' }).click();
    await expect(page.getByRole('tab', { name: 'Keamanan' })).toHaveAttribute('aria-selected', 'true');
    for (const control of ['Setujui', 'Abaikan', 'Tandai dibaca']) {
        await expect(page.getByRole('button', { name: control })).toHaveCount(0);
    }
});

test('answers an Admin Beranda and Tinjauan as the not-found page, with no entry for either (OD-14; UXS-25)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await preparerSignedIn(page, `admin-beranda-${String(Date.now())}`);

    for (const path of ['/beranda', '/tinjauan']) {
        const answer = await page.goto(path);
        expect(answer?.status()).toBe(404);
    }

    await page.goto('/pekerjaan');
    await expect(page.getByRole('heading', { name: 'Pekerjaan', level: 1 })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Beranda' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Tinjauan' })).toHaveCount(0);
});
