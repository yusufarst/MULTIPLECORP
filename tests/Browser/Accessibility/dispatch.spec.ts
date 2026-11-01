import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { dispatchRun } from '../dispatch-fixtures';

const expect = base.configure({ timeout: 60_000 });

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

// TL-12 (TASK-041; UXS-22): PG-023 and PG-024 with no WCAG 2.2 AA violation, the relation marker read as text.

test.describe.configure({ timeout: 300_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-023 and PG-024 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await dispatchRun(page, `keluar-a11y-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 844 });

        await page.goto(`/gudang/barang-keluar?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText(run.name);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/gudang/barang-keluar/${run.project}`);
        await expect(page.locator('[data-slot="dispatch-lot"]').filter({ hasText: run.other_code })).toContainText('perusahaan lain');
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
