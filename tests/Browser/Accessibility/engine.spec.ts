import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// TL-12's automated engine inside the browser suite (TEST_STRATEGY §11; TASK-004): axe-core on both
// engines against the WCAG 2.2 AA rules. Until the first page exists it checks the one page the stack
// serves, the framework's "not found" page; TASK-066 covers every page.
test('finds no WCAG 2.2 AA violation on the page the stack serves', async ({ page }) => {
    await page.goto('/no-such-page');

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
    expect(results.passes.length).toBeGreaterThan(0);
});
