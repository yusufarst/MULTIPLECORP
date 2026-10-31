import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-052; PG-047; DESIGN_SYSTEM §15): PG-047 with its form open and its confirmation at the desktop and phone widths.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-047 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await invoiceRun(page, `potong-a11y-${String(width)}-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '900000', apply: true, claim: '30000', proven: '30000', settled: '10000' });

        await page.goto(`/keuangan/potongan?perusahaan=${run.company}`);
        await expect(page.getByTestId('deduction-waiting')).toContainText('Rp20.000');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByTestId('deduction-waiting').getByRole('button', { name: 'Catat Potongan' }).click();
        const form = page.getByRole('dialog', { name: 'Catat Potongan' });
        await expect(form.getByLabel(/^Jenis potongan/).locator('option')).toHaveCount(2);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
