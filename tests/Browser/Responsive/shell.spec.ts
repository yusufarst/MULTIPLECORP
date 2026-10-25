import { expect, test } from '@playwright/test';

// TL-11 (TASK-016): the shell at the edges of DESIGN_SYSTEM §6's bands in both languages — the phone's bars below
// 768 px, the sidebar from 768 px; no horizontal scroll and no clipped entry.

const BANDS = [320, 390, 767, 768, 1024, 1025, 1280, 1680];

for (const lang of ['id', 'en'] as const) {
    test(`holds the shell in ${lang} at every band edge`, async ({ page }) => {
        await page.goto(`/__fixtures/shell?persona=owner&run=bands&bahasa=${lang}`);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

        for (const width of BANDS) {
            await page.setViewportSize({ width, height: 900 });

            const layout = await page.evaluate(() => ({
                scroll: document.documentElement.scrollWidth > document.documentElement.clientWidth,
                navs: [...document.querySelectorAll('nav')].filter((nav) => nav.checkVisibility()).length,
                clipped: [...document.querySelectorAll('nav a span, nav button')]
                    .filter((element) => element.checkVisibility() && !element.classList.contains('sr-only'))
                    .filter((element) => element.scrollWidth > element.clientWidth + 1)
                    .map((element) => element.textContent),
            }));

            expect(layout.scroll, `${lang} at ${String(width)} px`).toBe(false);
            expect(layout.navs, `${lang} at ${String(width)} px`).toBe(1);
            expect(layout.clipped, `${lang} at ${String(width)} px`).toEqual([]);
        }
    });
}
