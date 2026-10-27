import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';

// TL-12 (TASK-024): PG-060 — the directory, an account's profile with its state action, its sessions and the create
// form — passes the WCAG 2.2 AA checks at the phone and desktop bands.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 90_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-060 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const owner = await signedIn(page, `pengguna-a11y-${String(width)}`, { owner: true });

        await page.goto('/pengaturan/pengguna');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pengguna & Akses');
        await page.getByRole('button', { name: 'Buat Akun' }).click();
        await expect(page.getByLabel(/^E-mail/)).toBeVisible();
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/pengaturan/pengguna?akun=${owner.id}&bagian=profil`);
        await expect(page.getByRole('tab', { name: 'Profil' })).toBeVisible({ timeout: 15_000 });
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByRole('tab', { name: 'Sesi' }).click();
        await expect(page.getByText('sesi Anda saat ini', { exact: false })).toBeVisible({ timeout: 15_000 });
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
