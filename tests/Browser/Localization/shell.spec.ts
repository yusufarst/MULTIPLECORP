import { expect, test } from '@playwright/test';

// TL-10 (TASK-016): the shell's labels in both languages — no key shown as itself, the document title
// "{nama layar} · MultipleCorp" (H7-10).

const KEY_SHOWN = /\b(messages|glossary|shell|pages|shortcuts|outcome|actions)\.[a-z0-9_]+/;

for (const [lang, entry] of [['id', 'Pekerjaan'], ['en', 'Work']] as const) {
    test(`renders the shell in ${lang}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(`/__fixtures/shell?persona=admin&run=lang&bahasa=${lang}`);

        await expect(page.locator('html')).toHaveAttribute('lang', lang);
        await expect(page.getByRole('navigation').first().getByRole('link').first()).toHaveText(entry);
        await expect(page).toHaveTitle(/ · MultipleCorp$/);
        expect(await page.locator('body').innerText()).not.toMatch(KEY_SHOWN);
    });
}
