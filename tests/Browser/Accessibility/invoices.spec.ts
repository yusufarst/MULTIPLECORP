import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-049; PG-041–PG-043; DESIGN_SYSTEM §12.1, §15; INT-012): PG-041, PG-042 and PG-043 with its correction dialog pass the WCAG 2.2 AA checks at both bands, each measured at rest; the state says itself in words beside its icon.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on the invoice's pages at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await invoiceRun(page, `invoice-a11y-${String(width)}-${String(Date.now())}`, { stage: 'issued', payment: true });

        await page.goto(`/keuangan/invoice?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText('Belum Ditagihkan');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
        await expect(page.getByTestId('position')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByRole('button', { name: 'Tindakan lain' }).click();
        await page.getByRole('menuitem', { name: 'Batalkan Invoice' }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
        await page.keyboard.press('Escape');

        await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);
        await expect(page.locator('[data-slot="document-composer"]')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
