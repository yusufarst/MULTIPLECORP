import { expect, test } from '@playwright/test';

import { auditPage } from '../localization-audit';
import { reportRun } from '../report-fixtures';

// TL-10 (TASK-060; LOCALIZATION §3, §3.4; DOD-16): PG-051, PG-052 and PG-053 in both languages — keys of the catalogue,
// never hardcoded copy —; the filters address keys that stay across the language; the export file stays Indonesian in
// both (asserted by the integration suite on the file itself).

test.describe.configure({ timeout: 300_000 });

for (const [locale, texts] of [
    ['id', { reports: 'Laporan', sales: 'Invoice terbit', period: 'Periode', consolidated: 'Laporan Gabungan', figures: 'Angka per perusahaan dan gabungan', exports: 'Ekspor Saya', empty: 'Belum ada ekspor. Minta ekspor dari Laporan.' }],
    ['en', { reports: 'Reports', sales: 'Issued invoices', period: 'Period', consolidated: 'Consolidated report', figures: 'Figures by company and consolidated', exports: 'My exports', empty: 'No exports yet. Request one from Reports.' }],
] as const) {
    test(`shows PG-051, PG-052 and PG-053 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await reportRun(page, `laporan-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/laporan?laporan=penjualan&perusahaan=${run.code}`);
        await expect(page.getByRole('heading', { name: texts.reports, exact: true })).toBeVisible();
        await expect(page.getByLabel(texts.period)).toBeVisible();
        await expect(page.locator('[data-table="penjualan"]').getByRole('heading', { name: texts.sales })).toBeVisible();
        await auditPage(page, locale, 'PG-051');

        await page.goto(`/laporan/gabungan?perusahaan=${run.code}`);
        await expect(page.getByRole('heading', { name: texts.consolidated, exact: true })).toBeVisible();
        await expect(page.locator('[data-table="angka"]').getByRole('heading', { name: texts.figures })).toBeVisible();
        await auditPage(page, locale, 'PG-052');

        await page.goto('/laporan/ekspor');
        await expect(page.getByRole('heading', { name: texts.exports, exact: true })).toBeVisible();
        await expect(page.getByText(texts.empty)).toBeVisible();
        await auditPage(page, locale, 'PG-053');
    });
}
