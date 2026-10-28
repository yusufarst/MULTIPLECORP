import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { projectRun } from '../project-fixtures';

// TL-12 (TASK-032; PG-010, PG-011, PG-013): the project list with its journey summary, the journey with its three
// deferred groups and *Tindakan Lain*, and the form pass the WCAG 2.2 AA checks at both bands, each measured at rest.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-010, PG-011 and PG-013 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await projectRun(page, `proyek-a11y-${String(width)}-${String(Date.now())}`, { project: true });
        const content = page.locator('#konten');

        await page.goto('/proyek');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Proyek');
        await expect(content).toContainText(run.number ?? '');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/proyek/${run.project ?? ''}`);
        await expect(content).toContainText('Proyek dibuat');
        await expect(content).toContainText('Tidak ada syarat yang belum terpenuhi.');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto('/proyek/baru');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Proyek Baru');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
