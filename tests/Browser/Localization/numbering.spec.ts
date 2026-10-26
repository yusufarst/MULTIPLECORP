import { expect, test } from '@playwright/test';

import { clearGoLive, numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-10 (TASK-021; LOCALIZATION §3.7; DOD-16): PG-061 in both languages — its title, the go-live state and the
// scheme form's labels in the account's language, the format's tokens unchanged.

test.afterEach(async ({ page }) => {
    await clearGoLive(page);
});

for (const locale of ['id', 'en'] as const) {
    test(`shows PG-061 in ${locale === 'id' ? 'Indonesian' : 'English'}`, async ({ page }) => {
        await ownerSignedIn(page, `pen-l10n-${locale}-${String(Date.now())}`, locale);
        const company = await numberingCompany(page, null);

        await page.goto(`/pengaturan/penomoran?perusahaan=${company}&jenis=doc-06`);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(locale === 'id' ? 'Penomoran Dokumen' : 'Document numbering');
        await expect(page.getByText(locale === 'id' ? 'Go-live belum dicatat. Penomoran dimulai setelah go-live dicatat saat peralihan sistem.' : 'Go-live is not recorded. Numbering starts once go-live is recorded at the system cutover.')).toBeVisible();

        await page.getByRole('button', { name: locale === 'id' ? 'Tambah Format Nomor' : 'Add Number Format' }).click();
        await expect(page.getByLabel(locale === 'id' ? 'Templat nomor' : 'Number template')).toBeVisible();
        await expect(page.getByText(locale === 'id' ? 'Wajib memuat {nomor} tepat sekali' : 'Must contain {nomor} exactly once', { exact: false })).toBeVisible();
    });
}
