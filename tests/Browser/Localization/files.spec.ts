import { expect, test } from '@playwright/test';

import { issuedDocument } from '../files-fixtures';
import { ownerSignedIn } from '../numbering-fixtures';

// TL-10 (TASK-022; LOCALIZATION §3; DOD-16): the upload field and the rendition's status texts in both languages —
// keys of the catalogue, never hardcoded copy.

for (const [locale, texts] of [
    ['id', { label: 'Bukti', choose: 'Pilih File', ready: 'PDF Siap', owed: 'PDF Sedang Dibuat' }],
    ['en', { label: 'Evidence', choose: 'Choose file', ready: 'PDF ready', owed: 'PDF being created' }],
] as const) {
    test(`shows the upload field and the rendition states in ${locale}`, async ({ page }) => {
        await ownerSignedIn(page, `berkas-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/__fixtures/berkas?versi=${await issuedDocument(page, false)}`);
        await expect(page.getByLabel(texts.label)).toHaveCount(1);
        await expect(page.getByRole('button', { name: texts.choose })).toBeVisible();
        await expect(page.getByText(texts.owed)).toBeVisible();

        await page.goto(`/__fixtures/berkas?versi=${await issuedDocument(page, true)}`);
        await expect(page.getByText(texts.ready)).toBeVisible();
    });
}
