import { expect, test } from '@playwright/test';

import { quotationRun } from '../quotation-fixtures';

// TL-10 (TASK-033; LOCALIZATION §3, §3.4; DOD-16): PG-014 and PG-037 in both languages — keys of the catalogue, never
// hardcoded copy —, the quotation's paper staying Indonesian whatever the page's language.

for (const [locale, texts] of [
    ['id', { revisions: 'Riwayat revisi', save: 'Simpan Draf', issue: 'Terbitkan Penawaran', valid: 'Berlaku sampai', list: 'Daftar Penawaran', draft: 'Draf' }],
    ['en', { revisions: 'Revision history', save: 'Save draft', issue: 'Issue Quotation', valid: 'Valid until', list: 'Quotations', draft: 'Draft' }],
] as const) {
    test(`shows PG-014 and PG-037 in ${locale}, the paper in Indonesian`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await quotationRun(page, `penawaran-${locale}-${String(Date.now())}`, { stage: 'draft', locale });
        const content = page.locator('#konten');

        await page.goto(`/proyek/${run.project}/penawaran`);
        await expect(content).toContainText(texts.revisions);
        await expect(page.getByLabel(new RegExp(`^${texts.valid}`))).toBeVisible();
        await expect(page.getByRole('button', { name: texts.save })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.issue })).toBeVisible();
        await expect(page.locator('[data-slot="paper-frame"]').first()).toContainText('Penawaran Harga');
        await expect(page.locator('[data-slot="paper-frame"]').first()).toContainText('Berlaku sampai');

        await page.goto('/dokumen/penawaran');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.list);
        await expect(page.getByRole('row').filter({ hasText: run.number })).toContainText(texts.draft);
    });
}
