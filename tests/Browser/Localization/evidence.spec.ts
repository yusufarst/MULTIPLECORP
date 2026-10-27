import { expect, test } from '@playwright/test';

import { evidenceCompany } from '../evidence-fixtures';
import { ownerSignedIn } from '../numbering-fixtures';

// TL-10 (TASK-030; LOCALIZATION §3; DOD-16): PG-038 and its upload sheet in both languages — keys of the catalogue,
// never hardcoded copy.

for (const [locale, texts] of [
    ['id', { title: 'Bukti', empty: 'Belum ada bukti di cabang ini.', upload: 'Unggah', sheet: 'Unggah Dokumen', type: 'Jenis bukti', register: 'Daftarkan Bukti' }],
    ['en', { title: 'Evidence', empty: 'There is no evidence in this branch yet.', upload: 'Upload', sheet: 'Upload Document', type: 'Evidence type', register: 'Register Evidence' }],
] as const) {
    test(`shows PG-038 and its upload sheet in ${locale}`, async ({ page }) => {
        await ownerSignedIn(page, `bukti-${locale}-${String(Date.now())}`, locale);
        const { company } = await evidenceCompany(page);

        await page.goto(`/dokumen/bukti?cabang=${company}`);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.title);
        await expect(page.getByText(texts.empty)).toBeVisible();

        await page.getByRole('button', { name: texts.upload, exact: true }).filter({ visible: true }).first().click();
        const sheet = page.getByRole('dialog', { name: texts.sheet });
        await expect(sheet.getByLabel(texts.type)).toBeVisible();
        await expect(sheet.getByRole('button', { name: texts.register })).toBeVisible();
    });
}
