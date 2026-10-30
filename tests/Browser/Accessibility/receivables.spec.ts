import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-050; PG-044; PG-043; DESIGN_SYSTEM §12.1, §15): PG-044 with its tabs, and PG-043 with its billing, dispute and write-off dialogs, pass the WCAG 2.2 AA checks at both bands, each measured at rest; the due state says itself in words beside its icon.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on the receivable's pages at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await invoiceRun(page, `piutang-a11y-${String(width)}-${String(Date.now())}`, { stage: 'issued', billed: true, credit: '50000' });

        await page.goto(`/keuangan/piutang?perusahaan=${run.company}`);
        await expect(page.getByTestId('subtotals')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
        await expect(page.getByTestId('billing')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByRole('button', { name: 'Tindakan lain' }).click();
        await page.getByRole('menuitem', { name: 'Hapuskan Piutang' }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
        await page.keyboard.press('Escape');

        await page.getByRole('button', { name: 'Tindakan lain' }).click();
        await page.getByRole('menuitem', { name: 'Koreksi Penagihan' }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
