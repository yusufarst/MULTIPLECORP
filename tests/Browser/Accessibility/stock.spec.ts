import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { stockRun } from '../stock-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-040; DESIGN_SYSTEM §15; PT-17): PG-018–PG-020 and the reversal's confirmation at the desktop and phone widths; the quantity block's figures read with their labels.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 300_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-018–PG-020 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await stockRun(page, `stok-a11y-${String(width)}-${String(Date.now())}`);
        await page.setViewportSize({ width, height: 900 });

        await page.goto('/gudang/stok');
        await page.locator('form[role="search"]').getByLabel('Cari produk').fill(run.name);
        await page.locator('form[role="search"]').getByRole('button', { name: 'Cari', exact: true }).click();
        await expect(page.locator('#konten')).toContainText(run.name);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/gudang/stok/${run.product}`);
        const block = page.locator('[data-slot="inventory-block"]');
        await expect(block).toContainText('Stok fisik');
        await expect(block).toContainText('Direservasi');
        await expect(block).toContainText('Tidak layak pakai');
        await expect(block).toContainText('Tersedia');
        await expect(page.locator('[data-slot="product-lots"]')).toContainText(run.lot);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/gudang/riwayat-stok?produk=${run.product}&jenis=CONDITION_CHANGE`);
        await page.getByRole('button', { name: 'Balikkan', exact: true }).first().click();
        await expect(page.getByRole('alertdialog', { name: 'Balikkan pergerakan ini?' })).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).include('[role="alertdialog"]').analyze()).violations).toEqual([]);
    });
}
