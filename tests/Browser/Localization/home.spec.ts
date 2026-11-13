import { expect as base, test } from '@playwright/test';

import { importRun } from '../import-fixtures';
import { auditPage } from '../localization-audit';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-058; LOCALIZATION §3.4): Beranda, Pekerjaan and Tinjauan in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { home: 'Beranda', decisions: 'Perlu keputusan Anda', work: 'Pekerjaan', review: 'Tinjauan', conditions: 'Kondisi yang Masih Terbuka' }],
    ['en', { home: 'Home', decisions: 'Needs your decision', work: 'Work', review: 'Review', conditions: 'Conditions still open' }],
] as const) {
    test(`shows the home pages in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await importRun(page, `beranda-${locale}-${String(Date.now())}`, locale);

        await page.goto('/beranda');
        await expect(page.getByRole('heading', { name: texts.home, level: 1 })).toBeVisible();
        await expect(page.getByRole('region', { name: texts.decisions })).toBeVisible();
        await auditPage(page, locale, 'PG-006');
        await page.goto('/pekerjaan');
        await expect(page.getByRole('heading', { name: texts.work, level: 1 })).toBeVisible();
        await auditPage(page, locale, 'PG-007');
        await page.goto('/tinjauan');
        await expect(page.getByRole('region', { name: texts.conditions })).toBeVisible();
        await auditPage(page, locale, 'PG-008');
    });
}
