import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// TL-12 (TASK-014): the switch's name and state announced in both languages — a group named by GL-198
// "Bahasa tampilan", the current language pressed —, and no WCAG 2.2 AA violation on its page.

for (const [lang, group] of [['id', 'Bahasa tampilan'], ['en', 'Display language']] as const) {
    test(`announces the switch's name and state in ${lang}`, async ({ page }) => {
        await page.goto(`/__fixtures/bahasa?bahasa=${lang}`);

        const switcher = page.getByRole('group', { name: group });
        await expect(switcher.getByRole('button', { name: lang.toUpperCase(), pressed: true })).toBeVisible();
        await expect(switcher.getByRole('button', { name: lang === 'id' ? 'EN' : 'ID', pressed: false })).toBeVisible();

        const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
        expect(results.violations).toEqual([]);
    });
}
