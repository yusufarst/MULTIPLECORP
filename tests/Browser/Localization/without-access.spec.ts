import { expect, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { auditPage } from '../localization-audit';

// TL-10 (TASK-067; TASK-016; TASK-058; ADMIN_FLOW IP-21; PERMISSIONS_MATRIX RG-02, RG-04; LOCALIZATION §3.6, §3.7): what only an
// account without a capability or a company grant sees, in both languages — PG-005 *Halaman Sistem* for an unknown address,
// inside the shell in the account's language (MSG-09), and PG-007 *Pekerjaan* with MSG-38. Every other state of a page is the
// Owner's to show: the OWNER role carries every capability of the catalogue (RG-02), so the Localization specs that sign in as
// the Owner render each page's every control.

test.describe.configure({ timeout: 180_000 });

for (const [locale, texts] of [
    ['id', { notFound: 'Halaman tidak ditemukan.', noAccess: 'Akun Anda belum memiliki akses perusahaan. Hubungi Owner.' }],
    ['en', { notFound: 'Page not found.', noAccess: 'Your account has no company access yet. Contact the Owner.' }],
] as const) {
    test(`shows PG-005 and PG-007 to an account without access in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await signedIn(page, `tanpa-akses-${locale}`, { plain: true, locale });

        const answer = await page.goto(`/tidak-ada-${String(Date.now())}`);
        expect(answer?.status()).toBe(404);
        await expect(page.locator('[data-system-page="not_found"]')).toContainText(texts.notFound, { timeout: 15_000 });
        await auditPage(page, locale, 'PG-005');

        await page.goto('/pekerjaan');
        await expect(page.locator('#konten')).toContainText(texts.noAccess, { timeout: 15_000 });
        await auditPage(page, locale, 'PG-007 without a company grant');
    });
}
