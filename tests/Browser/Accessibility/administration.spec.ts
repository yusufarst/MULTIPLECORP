import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { administrationRun } from '../administration-fixtures';
import { atRest } from '../evidence-fixtures';

// TL-12 (TASK-035; J-ADM-01; DESIGN_SYSTEM §12.1): PG-011's checklist with its "Tambah Persyaratan" dialog and PG-039 pass
// the WCAG 2.2 AA checks at both bands, each measured at rest; each status is carried by its shape and label as well as
// its colour.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on the checklist and PG-039 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await administrationRun(page, `administrasi-a11y-${String(width)}-${String(Date.now())}`);

        await page.goto(`/proyek/${run.project}`);
        const checklist = page.getByRole('list', { name: 'Persyaratan administrasi' });
        await expect(checklist.getByRole('listitem')).toHaveCount(3);
        // The status says itself in words beside its icon (DESIGN_SYSTEM §12.1; WCAG 1.4.1).
        await expect(checklist.locator('[data-status="administrative_requirement.MISSING"]').first()).toHaveText('Belum Ada');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByRole('button', { name: 'Tambah Persyaratan' }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
        await page.keyboard.press('Escape');

        await page.goto(`/dokumen/administrasi?cabang=${run.company}`);
        await expect(page.getByRole('heading', { name: 'Dokumen Administrasi' })).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
