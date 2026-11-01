import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { addPurchaseLine, chooseSupplier, purchaseRun } from '../purchase-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-037; PG-015–PG-017; DESIGN_SYSTEM §15): the line editor's focus order, the disabled save's reason announced, the pages and a correction's confirmation at the desktop and phone widths.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 300_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-015–PG-017 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await purchaseRun(page, `beli-a11y-${String(width)}-${String(Date.now())}`);

        await page.goto(`/pembelian/baru?perusahaan=${run.company}`);
        await expect(page.getByRole('button', { name: 'Simpan Pembelian' })).toHaveAttribute('aria-describedby', 'save-reason');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await chooseSupplier(page, run.supplier);
        await addPurchaseLine(page, 0, run.goods, '2', '1000');
        await page.getByRole('button', { name: 'Simpan Pembelian' }).click();
        await page.waitForURL(/\/pembelian\/[0-9a-f-]{36}$/);
        const detail = page.url();

        await page.setViewportSize({ width, height: 900 });
        await page.goto(detail);
        await expect(page.locator('[data-line="1"]')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByRole('button', { name: 'Tutup Sisa Pembelian' }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/pembelian?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText(run.supplier);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
