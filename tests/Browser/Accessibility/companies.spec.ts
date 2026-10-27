import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-12 (TASK-025; PG-059; PG-062): the company master — the directory and a company's readiness and sections — and the
// tax configuration pass the WCAG 2.2 AA checks at both bands, each measured at rest.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-059 and PG-062 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await ownerSignedIn(page, `perusahaan-a11y-${String(width)}-${String(Date.now())}`);
        const company = await numberingCompany(page, null);

        for (const path of [`/pengaturan/perusahaan?perusahaan=${company}`, `/pengaturan/perusahaan?perusahaan=${company}&bagian=rekening`, '/pengaturan/pajak?bagian=rule']) {
            await page.goto(path);
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            await atRest(page);
            expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
        }
    });
}
