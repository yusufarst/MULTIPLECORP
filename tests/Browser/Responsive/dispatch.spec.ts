import { expect as base, test } from '@playwright/test';

import { dispatchRun } from '../dispatch-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-041; J-INV-03; NAV-023; NAV-024): PG-023 and PG-024 without a sideways scroll, on the phone the lot list one tap per lot.

test.describe.configure({ timeout: 300_000 });

for (const width of [320, 390, 768, 1280]) {
    test(`shows PG-023 and PG-024 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await dispatchRun(page, `keluar-lebar-${String(width)}-${String(Date.now())}`);
        const fits = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);
        await page.setViewportSize({ width, height: 844 });

        await page.goto(`/gudang/barang-keluar?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText(run.name);
        expect(await fits()).toBe(true);

        await page.goto(`/gudang/barang-keluar/${run.project}`);
        const lot = page.locator('[data-slot="dispatch-lot"]').filter({ hasText: run.lot });
        await expect(lot).toBeVisible();
        expect(await fits()).toBe(true);
        await lot.getByRole('button').first().click();
        await expect(lot.getByRole('button').first()).toHaveAttribute('aria-pressed', 'true');
        expect(await fits()).toBe(true);
    });
}
