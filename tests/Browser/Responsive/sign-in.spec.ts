import { expect, test } from '@playwright/test';

// TL-11 (TASK-017): PG-001 and PG-003 at the edges of DESIGN_SYSTEM §6's bands in both languages — no horizontal
// scroll, the submit whole and its label unclipped.

const BANDS = [320, 390, 767, 768, 1024, 1280, 1680];

for (const lang of ['id', 'en'] as const) {
    for (const address of ['/masuk', '/atur-kata-sandi#AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA']) {
        test(`holds ${address.split('#')[0] ?? address} in ${lang} at every band edge`, async ({ page }) => {
            await page.goto(address.replace('#', `?bahasa=${lang}#`));
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

            for (const width of BANDS) {
                await page.setViewportSize({ width, height: 900 });

                const layout = await page.evaluate(() => ({
                    scroll: document.documentElement.scrollWidth > document.documentElement.clientWidth,
                    clipped: [...document.querySelectorAll('main button, main label')]
                        .filter((element) => element.checkVisibility() && element.scrollWidth > element.clientWidth + 1)
                        .map((element) => element.textContent),
                }));

                expect(layout.scroll, `${lang} at ${String(width)} px`).toBe(false);
                expect(layout.clipped, `${lang} at ${String(width)} px`).toEqual([]);
            }
        });
    }
}
