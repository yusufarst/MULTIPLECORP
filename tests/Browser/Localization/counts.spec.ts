import { expect as base, test } from '@playwright/test';

import { adjustmentRun } from '../adjustment-fixtures';
import { auditPage } from '../localization-audit';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-043): PG-026 and PG-029 in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { counts: 'Stok Opname', start: 'Mulai Hitungan', discrepancies: 'Selisih & Temuan', findings: 'Temuan surplus' }],
    ['en', { counts: 'Stock count', start: 'Start count', discrepancies: 'Discrepancies and findings', findings: 'Surplus findings' }],
] as const) {
    test(`shows PG-026 and PG-029 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await adjustmentRun(page, `opname-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/gudang/stok-opname?produk=${run.product}`);
        await expect(page.getByRole('heading', { name: new RegExp(texts.counts, 'i'), level: 1 })).toBeVisible();
        await expect(page.locator('[data-slot="count-open"]').getByRole('button', { name: texts.start, exact: true })).toBeVisible();
        await auditPage(page, locale, 'PG-026');

        await page.goto('/gudang/selisih-temuan');
        await expect(page.getByRole('heading', { name: new RegExp(texts.discrepancies, 'i'), level: 1 })).toBeVisible();
        await expect(page.locator('[data-slot="discrepancy-findings"]')).toContainText(texts.findings);
        await auditPage(page, locale, 'PG-029');
    });
}
