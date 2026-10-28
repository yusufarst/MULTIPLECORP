import { expect, test } from '@playwright/test';

import { documentDraft } from '../document-fixtures';

// TL-10 (TASK-029; LOCALIZATION §3.4; DIR-037 D7; architecture challenge 17a; criterion 10): PG-035 and PG-036 in both
// languages — the controls switch, the paper stays Indonesian.

for (const [locale, texts] of [
    ['id', { list: 'Dokumen', save: 'Simpan Draf', issue: 'Terbitkan SPK', preview: 'Pratinjau SPK, draf belum terbit' }],
    ['en', { list: 'Documents', save: 'Save draft', issue: 'Issue SPK', preview: 'SPK preview, draft not issued' }],
] as const) {
    test(`shows PG-035 and PG-036 in ${locale}, the paper Indonesian`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const draft = await documentDraft(page, `dokumen-${locale}-${String(Date.now())}`, 'DOC-09', locale);

        await page.goto(`/dokumen/${draft}`);
        await expect(page.getByRole('button', { name: texts.save })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.issue })).toBeVisible();
        const paper = page.getByRole('region', { name: texts.preview });
        await expect(paper).toContainText('DRAF — belum terbit');
        await expect(paper).toContainText('Surat Perintah Kerja');
        await expect(paper).toContainText('Hormat kami');

        await page.goto('/dokumen');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.list);
    });
}
