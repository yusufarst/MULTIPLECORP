import { expect as base, type Page, test } from '@playwright/test';

import { importRun } from '../import-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-057; PG-058 `[desk]`, readable on the phone — NV-06; NAV-058): PG-058 without a sideways scroll at the phone
// and the tablet widths, its commands on the desktop only, so its preparation sheet opens from the tablet width up.

test.describe.configure({ timeout: 240_000 });

async function opened(page: Page, width: number): Promise<void> {
    await page.setViewportSize({ width: 1280, height: 900 });
    await importRun(page, `impor-lebar-${String(width)}-${String(Date.now())}`);
    await page.setViewportSize({ width, height: 844 });

    await page.goto('/data-master/impor');
    await expect(page.getByRole('heading', { name: 'Impor Data Awal', level: 1 })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

test('shows PG-058 without a sideways scroll at 390 px, its commands on the desktop only', async ({ page }) => {
    await opened(page, 390);

    await expect(page.getByRole('button', { name: 'Siapkan Batch', exact: true }).filter({ visible: true })).toHaveCount(0);
});

test('shows PG-058 and its preparation sheet without a sideways scroll at 768 px', async ({ page }) => {
    await opened(page, 768);

    // The header's command, there whether the list is empty (its empty state offers the same command) or not.
    await page.locator('[data-slot="page-header"]').getByRole('button', { name: 'Siapkan Batch', exact: true }).click();
    await expect(page.getByRole('dialog', { name: 'Siapkan Batch' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});
