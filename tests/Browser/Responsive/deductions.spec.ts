import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-052; NV-06; NAV-047; PT-05; PT-06): PG-047 without a sideways scroll, a table from 768 px, dense rows on the phone; `[desk]`, readable on the phone.

test.describe.configure({ timeout: 240_000 });

for (const width of [320, 390, 1280]) {
    test(`shows PG-047 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 844 });
        const run = await invoiceRun(page, `potong-lebar-${String(width)}-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '900000', apply: true, claim: '30000', proven: '30000', settled: '10000' });
        const fits = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

        await page.goto(`/keuangan/potongan?perusahaan=${run.company}`);
        await expect(page.getByTestId('deduction-waiting')).toContainText('Rp20.000');
        await expect(page.locator('#konten')).toContainText('Rp10.000');
        expect(await fits()).toBe(true);

        if (width >= 768) {
            await expect(page.getByRole('table', { name: 'Potongan' })).toBeVisible();
        } else {
            await expect(page.getByRole('table', { name: 'Potongan' })).toHaveCount(0);
        }

        await page.getByTestId('deduction-waiting').getByRole('button', { name: 'Catat Potongan' }).click();
        await expect(page.getByRole('dialog', { name: 'Catat Potongan' })).toBeVisible();
        expect(await fits()).toBe(true);
    });
}
