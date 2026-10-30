import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { handoverRun } from '../handover-fixtures';

// TL-12 (TASK-047; PG-033; DESIGN_SYSTEM §12.1, §15; INT-012): PG-033 with its reversal dialog passes the WCAG 2.2 AA checks at both
// bands, each measured at rest; the paper a named region, never a live region, its frame reachable by keyboard.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-033 and its dialog at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await handoverRun(page, `serah-a11y-${String(width)}-${String(Date.now())}`, { recorded: true });

        await page.goto(`/proyek/${run.project}/serah-terima`);
        await expect(page.getByTestId('handover')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByTestId('handover').getByRole('button', { name: 'Balikkan' }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}

test('names the paper as a region, never a live one, its frame reachable by keyboard (DESIGN_SYSTEM §15; INT-012)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await handoverRun(page, `serah-kertas-${String(Date.now())}`);

    await page.goto(`/proyek/${run.project}/serah-terima`);
    const paper = page.getByRole('region', { name: /^Pratinjau BAST/ });
    await expect(paper).toBeVisible();
    expect(await paper.getAttribute('aria-live')).toBeNull();
    await expect(page.locator('[data-slot="paper-frame"]').first()).toHaveAttribute('tabindex', '0');
});
