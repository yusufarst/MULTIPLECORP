import { expect as base, test } from '@playwright/test';

import { currentLine, receivingRun } from '../receiving-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-039; D-UX-11; D-DS-08; UXS-35; NAV-021; NAV-022): PG-021 and PG-022 without a sideways scroll; on the phone the checklist, one line at a time, the scan field fixed at the bottom and touch-size controls; on the desktop the lines on the left and the current line on the right.

test.describe.configure({ timeout: 300_000 });

for (const width of [320, 390, 768, 1280]) {
    test(`shows PG-021 and PG-022 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 844 });
        const run = await receivingRun(page, `terima-lebar-${String(width)}-${String(Date.now())}`);
        const fits = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

        await page.goto('/gudang/barang-masuk');
        await expect(page.locator('#konten')).toContainText(run.supplier);
        expect(await fits()).toBe(true);

        await page.goto(`/gudang/barang-masuk/${run.purchase}`);
        await expect(currentLine(page)).toBeVisible();
        expect(await fits()).toBe(true);

        const field = page.locator('[data-slot="scan-field"]');
        const box = await field.boundingBox();
        const list = await page.locator('[data-slot="receive-lines"]').boundingBox();
        const form = await currentLine(page).boundingBox();
        expect(box).not.toBeNull();
        expect(list).not.toBeNull();
        expect(form).not.toBeNull();

        if (width < 768) {
            // Fixed at the bottom above the action bar, the lines stacked over the line in progress.
            expect(await field.evaluate((element) => getComputedStyle(element).position)).toBe('fixed');
            expect((box?.y ?? 0) + (box?.height ?? 0)).toBeLessThanOrEqual(844);
            expect(list?.y ?? 0).toBeLessThan(form?.y ?? 0);
        } else {
            expect((list?.x ?? 0) + (list?.width ?? 0)).toBeLessThanOrEqual(form?.x ?? 0);
        }
    });
}

test.describe('with a coarse pointer', () => {
    test.use({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 } });

    test('gives the line\'s controls a touch size, the last one clear of the scan field (UXS-35; GR-07; D-DS-19)', async ({ page }) => {
        const run = await receivingRun(page, `terima-sentuh-${String(Date.now())}`);
        await page.goto(`/gudang/barang-masuk/${run.purchase}`);
        expect(await page.evaluate(() => window.matchMedia('(pointer: coarse)').matches)).toBe(true);

        const record = currentLine(page).getByRole('button', { name: 'Catat Barang Masuk' });
        await record.scrollIntoViewIfNeeded();
        expect((await record.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);
        expect((await currentLine(page).getByRole('textbox', { name: 'Layak pakai' }).boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);
        expect((await page.getByRole('button', { name: 'Cari Produk' }).boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);

        // Scrolled to the end, the action sits above the docked field.
        await page.evaluate(() => { window.scrollTo(0, document.documentElement.scrollHeight); });
        const bottom = await record.boundingBox();
        const dock = await page.locator('[data-slot="scan-field"]').boundingBox();
        expect((bottom?.y ?? 0) + (bottom?.height ?? 0)).toBeLessThanOrEqual(dock?.y ?? 0);
    });
});
