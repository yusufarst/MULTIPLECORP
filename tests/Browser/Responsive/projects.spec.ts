import { expect, test } from '@playwright/test';

import { projectRun } from '../project-fixtures';

// TL-11 (TASK-032; NAV-010, NAV-011, NAV-013 `[both]`): the project list as rows, the journey with its header, next step
// and track first, and the form in one column on the phone, each without a sideways scroll at every band edge.

for (const width of [320, 390, 768, 1280, 1680]) {
    test(`shows PG-010, PG-011 and PG-013 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await projectRun(page, `proyek-lebar-${String(width)}-${String(Date.now())}`, { project: true });
        const unscrolled = async () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

        await page.goto('/proyek');
        await expect(page.locator('#konten')).toContainText(run.number ?? '');
        expect(await unscrolled()).toBe(true);

        await page.goto(`/proyek/${run.project ?? ''}`);
        await expect(page.locator('#konten')).toContainText('Proyek dibuat');
        expect(await unscrolled()).toBe(true);

        await page.goto(`/proyek/${run.project ?? ''}/ubah`);
        await expect(page.getByLabel(/^Judul proyek/)).toBeVisible();
        expect(await unscrolled()).toBe(true);
    });
}
