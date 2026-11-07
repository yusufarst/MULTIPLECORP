import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { stockRun } from '../stock-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-059; WCAG 2.2 AA; IP-15): PG-009 with its results and with an unknown scanned code, the scan answer
// assertive and focus held in the field.

test.describe.configure({ timeout: 300_000 });

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-009 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await stockRun(page, `cari-a11y-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 900 });

        await page.goto('/cari');
        const field = page.locator('#konten').getByRole('textbox', { name: 'Cari atau pindai kode' });
        await field.fill(run.sku);
        await expect(page.locator('[data-slot="search-exact"]')).toContainText(run.name);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await field.fill('');
        await page.keyboard.type(`TIDAKADA${String(width)}${String(Date.now())}`, { delay: 5 });
        await page.keyboard.press('Enter');
        await expect(page.locator('[data-slot="scan-unknown"]').getByRole('alert')).toBeVisible();
        await expect(field).toBeFocused();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
