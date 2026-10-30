import { expect, test } from '@playwright/test';

import { handoverRun } from '../handover-fixtures';

// TL-11 (TASK-047; D-DS-23; INT-012; DC-11; NAV-033; criterion 3): the handover's form beside the BAST's live preview at 1,025, 1,279 and
// 1,280 px — part of the page, never a tab, no sideways scroll —; "Rincian | Pratinjau" at 1,024 px; the phone without a sideways scroll.

for (const width of [1025, 1279, 1280]) {
    test(`keeps the form beside the live preview at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await handoverRun(page, `serah-lebar-${String(width)}-${String(Date.now())}`, { lines: 3 });
        await page.goto(`/proyek/${run.project}/serah-terima`);

        await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'desktop');
        await expect(page.getByRole('tab')).toHaveCount(0);
        const paper = page.getByRole('region', { name: /^Pratinjau BAST/ });
        await expect(paper).toContainText('Berita Acara Serah Terima');
        const form = await page.getByLabel(/^Penerima/).boundingBox();
        expect(((await paper.boundingBox())?.x ?? 0) > (form?.x ?? 0)).toBe(true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}

test('offers "Rincian | Pratinjau" at 1,024 px', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 900 });
    const run = await handoverRun(page, `serah-1024-${String(Date.now())}`);
    await page.goto(`/proyek/${run.project}/serah-terima`);

    await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'tablet');
    await page.getByRole('tab', { name: 'Pratinjau' }).click();
    await expect(page.getByRole('region', { name: /^Pratinjau BAST/ })).toContainText('Berita Acara Serah Terima');
});

for (const width of [320, 390]) {
    test(`shows PG-033 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 844 });
        const run = await handoverRun(page, `serah-hp-lebar-${String(width)}-${String(Date.now())}`, { recorded: true });
        await page.goto(`/proyek/${run.project}/serah-terima`);

        await expect(page.getByTestId('handover')).toBeVisible();
        await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'phone');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
