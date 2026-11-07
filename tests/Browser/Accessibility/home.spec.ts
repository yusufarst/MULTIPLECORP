import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { importRun } from '../import-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-058; WCAG 2.2 AA; DESIGN_SYSTEM §15): Beranda, Pekerjaan and Tinjauan with no axe violation once their
// groups have arrived; the figures as a table, status never by colour alone.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 240_000 });

test('keeps the home pages and the review queue free of axe violations', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await importRun(page, `beranda-a11y-${String(Date.now())}`);

    await page.goto('/beranda');
    await expect(page.getByRole('table', { name: 'Angka Periode' })).toBeVisible();
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

    await page.goto('/pekerjaan');
    await expect(page.getByRole('heading', { name: 'Pekerjaan', level: 1 })).toBeVisible();
    await expect(page.locator('[data-region-state="loading"]')).toHaveCount(0);
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

    await page.goto('/tinjauan');
    await expect(page.getByRole('region', { name: 'Kondisi yang Masih Terbuka' })).toBeVisible();
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
});
