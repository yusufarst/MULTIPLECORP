import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-11 (TASK-051; NV-06; NAV-045; NAV-046; NAV-050; PT-05; PT-06): PG-045, PG-046 and PG-050 without a sideways scroll; a table from 768 px, dense rows on the phone; PG-046 one column on the phone with the applications beside the payment on the desktop (J-FIN-03).

test.describe.configure({ timeout: 240_000 });

for (const width of [320, 390, 1280]) {
    test(`shows PG-045, PG-046 and PG-050 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 844 });
        const run = await invoiceRun(page, `bayar-lebar-${String(width)}-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '250000', line: '900000' });
        const fits = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

        await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText('Rp250.000');
        expect(await fits()).toBe(true);

        if (width >= 768) {
            await expect(page.getByRole('table', { name: 'Pembayaran' })).toBeVisible();
        } else {
            await expect(page.getByRole('table', { name: 'Pembayaran' })).toHaveCount(0);
        }

        await page.goto(`/keuangan/pembayaran/baru?invoice=${run.invoice ?? ''}`);
        await expect(page.getByTestId('application')).toHaveCount(1);
        expect(await fits()).toBe(true);

        await page.goto(`/keuangan/mutasi-bank?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText(`TRX-${run.code}`);
        expect(await fits()).toBe(true);
    });
}

test('sets PG-046\'s applications beside the payment on the desktop and below it on the phone (NAV-046; J-FIN-03)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `bayar-kolom-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true });

    await page.goto(`/keuangan/pembayaran/baru?invoice=${run.invoice ?? ''}`);
    const amount = await page.getByLabel(/^Jumlah/).boundingBox();
    const application = await page.getByTestId('application').boundingBox();
    expect(application?.x ?? 0).toBeGreaterThan((amount?.x ?? 0) + (amount?.width ?? 0) - 1);

    await page.setViewportSize({ width: 390, height: 844 });
    const below = await page.getByTestId('application').boundingBox();
    const field = await page.getByLabel(/^Jumlah/).boundingBox();
    expect(below?.y ?? 0).toBeGreaterThan(field?.y ?? 0);
});
