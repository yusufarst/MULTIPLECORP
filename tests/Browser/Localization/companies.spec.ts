import { expect, test } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-10 (TASK-025; LOCALIZATION §3; DOD-16): PG-059 and PG-062 in both languages — keys of the catalogue, never
// hardcoded copy.

for (const [locale, texts] of [
    ['id', { companies: 'Perusahaan', readiness: 'Kesiapan Perusahaan', accounts: 'Rekening Bank', tax: 'Pajak', rates: 'Tarif' }],
    ['en', { companies: 'Companies', readiness: 'Company Readiness', accounts: 'Bank Accounts', tax: 'Tax', rates: 'Rates' }],
] as const) {
    test(`shows PG-059 and PG-062 in ${locale}`, async ({ page }) => {
        await ownerSignedIn(page, `perusahaan-${locale}-${String(Date.now())}`, locale);
        const company = await numberingCompany(page, null);

        await page.goto('/pengaturan/perusahaan');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.companies);
        await page.goto(`/pengaturan/perusahaan?perusahaan=${company}`);
        await expect(page.getByRole('heading', { name: texts.readiness })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.accounts })).toBeVisible();

        await page.goto('/pengaturan/pajak');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.tax);
        await expect(page.getByRole('tab', { name: texts.rates })).toBeVisible();
    });
}
