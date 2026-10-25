import { expect, test } from '@playwright/test';

// TL-11 (TASK-015; acceptance criteria 2 and 6): the component gallery at the edges of DESIGN_SYSTEM §6's bands in
// both languages — no horizontal scroll of the page (a table scrolls only inside its own frame, WCAG 1.4.10's
// exception), no clipped button label, the phone's variants below 768 px and the desktop's from 768 px.

const BANDS = [320, 390, 767, 768, 1024, 1025, 1279, 1280, 1680];

for (const lang of ['id', 'en'] as const) {
    test(`holds the layout in ${lang} at every band edge`, async ({ page }) => {
        await page.goto(`/__fixtures/komponen?bahasa=${lang}`);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

        for (const width of BANDS) {
            await page.setViewportSize({ width, height: 900 });

            const layout = await page.evaluate(() => ({
                scroll: document.documentElement.scrollWidth > document.documentElement.clientWidth,
                clipped: [...document.querySelectorAll('main button')]
                    .filter((element) => element.checkVisibility() && element.closest('[data-slot=table-frame]') === null)
                    .filter((element) => element.scrollWidth > element.clientWidth + 1)
                    .map((element) => element.textContent),
                table: document.querySelector('[data-slot=table-frame]')?.checkVisibility() ?? false,
            }));

            expect(layout.scroll, `${lang} at ${String(width)} px`).toBe(false);
            expect(layout.clipped, `${lang} at ${String(width)} px`).toEqual([]);
            expect(layout.table, `${lang} at ${String(width)} px: the table from 768 px, rows below`).toBe(width >= 768);
        }
    });
}
