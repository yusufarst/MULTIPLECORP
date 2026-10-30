import { expect, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

// TL-11 (TASK-049; D-DS-23; INT-012; DC-11; NAV-041–NAV-043): an invoice's composer at 1,025, 1,279 and 1,280 px — the form beside the live paper, never a tab, no sideways scroll —; "Rincian | Pratinjau" at 1,024 px; PG-041 and PG-043 without a sideways scroll on the phone.

for (const width of [1025, 1279, 1280]) {
    test(`keeps the composer's form beside the live paper at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await invoiceRun(page, `invoice-lebar-${String(width)}-${String(Date.now())}`, { lines: 5 });
        await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);

        await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'desktop');
        await expect(page.getByRole('tab')).toHaveCount(0);
        const paper = page.getByRole('region', { name: /^Pratinjau Invoice/ });
        const form = await page.getByLabel(/^Keterangan/).boundingBox();
        expect(((await paper.boundingBox())?.x ?? 0) > (form?.x ?? 0)).toBe(true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}

test('offers "Rincian | Pratinjau" at 1,024 px', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 900 });
    const run = await invoiceRun(page, `invoice-1024-${String(Date.now())}`);
    await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);

    await expect(page.locator('[data-slot="document-composer"]')).toHaveAttribute('data-band', 'tablet');
    await page.getByRole('tab', { name: 'Pratinjau' }).click();
    await expect(page.getByRole('region', { name: /^Pratinjau Invoice/ })).toContainText('DRAF — belum terbit');
});

for (const width of [320, 390]) {
    test(`shows PG-041 and PG-043 without a sideways scroll at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 844 });
        const run = await invoiceRun(page, `invoice-hp-lebar-${String(width)}-${String(Date.now())}`, { stage: 'issued', payment: true });

        await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
        await expect(page.getByTestId('position')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await page.goto(`/keuangan/invoice?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText('Belum Ditagihkan');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
