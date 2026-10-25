import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// TL-12 (TASK-016): the shell — skip link, landmarks, names and focus order — passes the WCAG 2.2 AA checks at the
// phone and desktop bands, with *Lainnya* open on the phone.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation in the shell at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/__fixtures/shell?persona=owner&run=a11y');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.getByRole('main')).toHaveCount(1);

        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        if (width < 768) {
            await page.getByRole('button', { name: 'Lainnya' }).click();
            await expect(page.getByRole('dialog')).toBeVisible();
            expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
        }
    });
}
