import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { reportRun, runExports } from '../report-fixtures';

// TL-12 (TASK-060; DESIGN_SYSTEM §12.1; GR-23): PG-051 with the receivable ageing's chart and its table, PG-052 and
// PG-053 with a ready export pass the WCAG 2.2 AA checks at both bands, each measured at rest — table headers, the
// chart hidden from assistive technology beside its table, the export states as text.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 300_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-051, PG-052 and PG-053 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await reportRun(page, `laporan-a11y-${String(width)}-${String(Date.now())}`);

        for (const report of ['penjualan', 'piutang', 'laba']) {
            await page.goto(`/laporan?laporan=${report}&perusahaan=${run.code}`);
            await expect(page.locator('[data-slot="report-table"]').first()).toBeVisible();
            await atRest(page);
            expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
        }

        await page.goto(`/laporan/gabungan?perusahaan=${run.code}`);
        await expect(page.locator('[data-table="angka"]')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/laporan?laporan=penjualan&perusahaan=${run.code}`);
        await page.getByRole('button', { name: 'Ekspor' }).click();
        await page.getByRole('menuitem', { name: 'Ekspor Excel' }).click();
        await page.waitForURL('**/laporan/ekspor');
        await runExports(page);
        await page.goto('/laporan/ekspor');
        await expect(page.locator('[data-slot="export-row"]').first()).toContainText('Siap Diunduh');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
