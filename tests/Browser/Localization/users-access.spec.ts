import { expect, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';

// TL-10 (TASK-024; LOCALIZATION §3.7; DOD-16): PG-060 in both languages — the title, the directory's action, an
// account's sections and the access section's line for the Owner's own account in the account's own language.

test.describe.configure({ timeout: 90_000 });

const COPY = {
    id: { title: 'Pengguna & Akses', create: 'Buat Akun', sections: ['Profil', 'Akses', 'Sesi', 'Tautan', 'Keamanan'], self: 'Akses akun Anda sendiri tidak diubah di sini.' },
    en: { title: 'Users and access', create: 'Create Account', sections: ['Profile', 'Access', 'Sessions', 'Links', 'Security'], self: 'Your own account\'s access is not changed here.' },
} as const;

for (const locale of ['id', 'en'] as const) {
    test(`shows PG-060 in ${locale === 'id' ? 'Indonesian' : 'English'}`, async ({ page }) => {
        const copy = COPY[locale];
        const owner = await signedIn(page, `pengguna-l10n-${locale}`, { owner: true, locale });

        await page.goto('/pengaturan/pengguna');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(copy.title);
        await expect(page.getByRole('button', { name: copy.create })).toBeVisible();

        await page.goto(`/pengaturan/pengguna?akun=${owner.id}&bagian=profil`);
        for (const section of copy.sections) {
            await expect(page.getByRole('tab', { name: section })).toBeVisible({ timeout: 15_000 });
        }

        await page.getByRole('tab', { name: copy.sections[1] }).click();
        await expect(page.getByText(copy.self)).toBeVisible({ timeout: 15_000 });
    });
}
