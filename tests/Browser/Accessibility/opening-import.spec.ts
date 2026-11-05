import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { importRun } from '../import-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-057; WCAG 2.2 AA; DESIGN_SYSTEM §15): PG-058 and its "Siapkan Batch" sheet with no axe violation, every field named, Esc closing the sheet with
// nothing sent and the focus back on the page.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 240_000 });

test('keeps PG-058 and its preparation sheet free of axe violations, each field named', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await importRun(page, `impor-a11y-${String(Date.now())}`);

    await page.goto('/data-master/impor');
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

    await page.getByRole('button', { name: 'Siapkan Batch', exact: true }).first().click();
    const sheet = page.getByRole('dialog', { name: 'Siapkan Batch' });
    await expect(sheet.getByLabel('Jenis data')).toBeVisible();
    await expect(sheet.getByLabel('Tanggal batas')).toBeVisible();
    await expect(sheet.getByLabel('File sumber')).toBeAttached();
    expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

    await page.keyboard.press('Escape');
    await expect(sheet).toBeHidden();
    await expect(page.getByRole('heading', { name: 'Impor Data Awal', level: 1 })).toBeVisible();
});
