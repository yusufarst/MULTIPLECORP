import { expect, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';

// TL-11 (TASK-024; NAV-060 `[desk]`; J-ACC-02; NV-06): PG-060 readable at every band — the directory and an account's
// sections without a page scroll sideways —, its quick actions "Akhiri Sesi" and "Nonaktifkan" reachable on the phone.

test.describe.configure({ timeout: 90_000 });

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`lays PG-060 out without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const owner = await signedIn(page, `pengguna-resp-${String(width)}`, { owner: true });

        await page.goto('/pengaturan/pengguna');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pengguna & Akses');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.goto(`/pengaturan/pengguna?akun=${owner.id}&bagian=profil`);
        await expect(page.getByRole('tab', { name: 'Profil' })).toBeVisible({ timeout: 15_000 });
        await expect(page.getByRole('button', { name: 'Nonaktifkan' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.getByRole('tab', { name: 'Sesi' }).click();
        await expect(page.getByText('sesi Anda saat ini', { exact: false })).toBeVisible({ timeout: 15_000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
