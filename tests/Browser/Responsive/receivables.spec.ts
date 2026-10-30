import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-050; NV-06; NAV-044; PT-05; PT-06): PG-044 and PG-043's billing section without a sideways scroll on the phone and at the desk; a table from 768 px, dense rows on the phone with the lookup of a client's receivables (J-FIN-02).

test.describe.configure({ timeout: 240_000 });

for (const width of [320, 390, 1280]) {
    test(`shows PG-044 and PG-043's receivable without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 844 });
        const run = await invoiceRun(page, `piutang-lebar-${String(width)}-${String(Date.now())}`, { stage: 'issued', billed: true, writtenOff: '100000' });

        await page.goto(`/keuangan/piutang?perusahaan=${run.company}`);
        await expect(page.getByTestId('subtotals')).toContainText('Rp900.000');
        await expect(page.getByLabel(/^Klien/)).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        if (width >= 768) {
            await expect(page.getByRole('table', { name: 'Piutang' })).toBeVisible();
        } else {
            await expect(page.getByRole('table', { name: 'Piutang' })).toHaveCount(0);
        }

        await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
        await expect(page.getByTestId('writeoffs')).toContainText('Rp100.000');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}

test('offers "Tagihkan" within the billing section on the phone (J-FIN-02)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await invoiceRun(page, `piutang-hp-${String(Date.now())}`, { stage: 'issued' });

    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page.getByTestId('billing').getByRole('button', { name: 'Tagihkan' })).toBeInViewport({ ratio: 0 });
});
