import { expect, test } from '@playwright/test';

// TL-11 (TASK-014): the page with the switch at the edges of DESIGN_SYSTEM §6's bands in both languages — no
// horizontal scroll, no clipped label, the switch's targets at a coarse pointer's 44 px (LOCALIZATION §3.7;
// TEST_STRATEGY §9). Each page's own bands are its Task's; TASK-065 completes them.

const BANDS = [320, 390, 767, 768, 1024, 1025, 1200, 1279, 1280, 1680];

for (const lang of ['id', 'en'] as const) {
    test(`holds the layout in ${lang} at every band edge`, async ({ page }) => {
        await page.goto(`/__fixtures/bahasa?bahasa=${lang}`);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

        for (const width of BANDS) {
            await page.setViewportSize({ width, height: 800 });

            const layout = await page.evaluate(() => ({
                scroll: document.documentElement.scrollWidth > document.documentElement.clientWidth,
                clipped: [...document.querySelectorAll('h1, button, label span')].filter((element) => element.scrollWidth > element.clientWidth).length,
                targets: [...document.querySelectorAll('[role="group"] button')].map((element) => {
                    const box = element.getBoundingClientRect();

                    return Math.min(box.width, box.height);
                }),
            }));

            expect(layout.scroll, `${lang} at ${String(width)} px`).toBe(false);
            expect(layout.clipped, `${lang} at ${String(width)} px`).toBe(0);
            expect(Math.min(...layout.targets), `${lang} at ${String(width)} px`).toBeGreaterThanOrEqual(44);
        }
    });
}
