import { expect, test } from '@playwright/test';

import { projectRun } from '../project-fixtures';

// TL-10 (TASK-032; LOCALIZATION §3; DOD-16): PG-010, PG-011 and PG-013 in both languages — keys of the catalogue, never
// hardcoded copy; the business data stays as entered.

for (const [locale, texts] of [
    ['id', { list: 'Proyek', track: 'Tahap proyek', stage: 'Kesepakatan', details: 'Rincian proyek', history: 'Proyek dibuat', form: 'Proyek Baru', lines: 'Barang dan jasa', create: 'Buat Proyek' }],
    ['en', { list: 'Projects', track: 'Project stages', stage: 'Agreement', details: 'Project details', history: 'Project created', form: 'New Project', lines: 'Goods and services', create: 'Create Project' }],
] as const) {
    test(`shows PG-010, PG-011 and PG-013 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await projectRun(page, `proyek-${locale}-${String(Date.now())}`, { project: true, locale });
        const content = page.locator('#konten');

        await page.goto('/proyek');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.list);
        await expect(page.getByRole('row').filter({ hasText: run.number ?? '' })).toContainText(texts.stage);

        await page.goto(`/proyek/${run.project ?? ''}`);
        await expect(page.getByRole('list', { name: texts.track })).toContainText(texts.stage);
        await expect(content).toContainText(texts.details);
        await expect(content).toContainText(texts.history);
        await expect(content).toContainText(run.goods);

        await page.goto('/proyek/baru');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.form);
        await expect(content).toContainText(texts.lines);
        await expect(page.getByRole('button', { name: texts.create })).toBeVisible();
    });
}
