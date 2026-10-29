import { expect, test } from '@playwright/test';

import { administrationRun } from '../administration-fixtures';

// TL-10 (TASK-035; LOCALIZATION §3; DOD-16): PG-011's checklist and PG-039 in both languages — keys of the catalogue,
// never hardcoded copy; the requirement's own description stays as entered.

for (const [locale, texts] of [
    ['id', { list: 'Persyaratan administrasi', add: 'Tambah Persyaratan', invoice: 'Invoice proyek', missing: 'Belum Ada', page: 'Dokumen Administrasi', tab: 'Semua', required: 'Wajib' }],
    ['en', { list: 'Administrative requirements', add: 'Add Requirement', invoice: 'Project invoice', missing: 'Missing', page: 'Administrative documents', tab: 'All', required: 'Required' }],
] as const) {
    test(`shows the checklist and PG-039 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await administrationRun(page, `administrasi-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/proyek/${run.project}`);
        const checklist = page.getByRole('list', { name: texts.list });
        await expect(checklist).toContainText(texts.invoice);
        await expect(checklist).toContainText(texts.missing);
        await expect(checklist).toContainText('SPK perusahaan untuk sekolah');
        await expect(page.getByRole('button', { name: texts.add })).toBeVisible();

        await page.goto(`/dokumen/administrasi?cabang=${run.company}`);
        await expect(page.getByRole('heading', { name: texts.page })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.tab })).toBeVisible();
        await expect(page.getByRole('table', { name: texts.page })).toContainText(texts.required);
    });
}
