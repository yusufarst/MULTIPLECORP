import { expect, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';

// TL-10 (TASK-023; LOCALIZATION §3.7; DOD-16): PG-065 and the account menu in both languages — the title, the
// sections, the language choice and the menu's items in the account's own language.

const COPY = {
    id: { title: 'Akun Saya', sections: ['Profil dan akses', 'Kata sandi', 'Verifikasi dua langkah', 'Kode pemulihan', 'Google', 'Aktivitas akun'], language: 'Bahasa tampilan', menu: /Menu akun/, logout: 'Keluar', replace: 'Ganti Perangkat' },
    en: { title: 'My account', sections: ['Profile and access', 'Password', 'Two-step verification', 'Recovery codes', 'Google', 'Account activity'], language: 'Display language', menu: /Account menu/, logout: 'Sign out', replace: 'Replace Device' },
} as const;

// A sign-in through PG-001 and PG-002, a step-up and the checks: longer than the default half minute on WebKit.
test.describe.configure({ timeout: 90_000 });

for (const locale of ['id', 'en'] as const) {
    test(`shows PG-065 and the account menu in ${locale === 'id' ? 'Indonesian' : 'English'}`, async ({ page }) => {
        const copy = COPY[locale];
        await signedIn(page, `akun-l10n-${locale}`, { locale });

        await page.goto('/akun');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(copy.title);

        for (const section of copy.sections) {
            await expect(page.getByRole('heading', { name: section, exact: true })).toBeVisible();
        }

        await expect(page.getByLabel(copy.language)).toHaveValue(locale);
        await expect(page.getByRole('button', { name: copy.replace })).toBeVisible();

        await page.getByRole('button', { name: copy.menu }).click();
        await expect(page.getByRole('menuitem', { name: copy.title })).toBeVisible();
        await expect(page.getByRole('menuitemcheckbox', { name: locale === 'id' ? 'Bahasa Indonesia' : 'Indonesian' })).toHaveAttribute('aria-checked', locale === 'id' ? 'true' : 'false');
        await expect(page.getByRole('menuitemcheckbox', { name: 'English' })).toHaveAttribute('aria-checked', locale === 'en' ? 'true' : 'false');
        await expect(page.getByRole('menuitem', { name: copy.logout })).toBeVisible();
    });
}
