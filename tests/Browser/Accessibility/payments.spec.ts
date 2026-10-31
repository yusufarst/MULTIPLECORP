import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-051; PG-045; PG-046; PG-050; DESIGN_SYSTEM §12.1, §15): PG-045 with its payment sheet and the contra's form, PG-046 and PG-050 with its recording sheet, on the phone and at the desk.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 240_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on the payment pages at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await invoiceRun(page, `bayar-a11y-${String(width)}-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '250000', apply: true, line: '900000' });

        await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText('Rp250.000');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.locator('#konten').getByRole('button', { name: width >= 768 ? /\d{4}|\d+ \w+/ : run.client_name }).first().click();
        await expect(page.getByTestId('payment')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.getByTestId('payment').getByRole('button', { name: 'Batalkan Catatan Pembayaran (Kontra)' }).click();
        await expect(page.getByRole('dialog', { name: 'Batalkan Catatan Pembayaran (Kontra)' })).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/keuangan/pembayaran/baru?invoice=${run.invoice ?? ''}`);
        await expect(page.getByTestId('application')).toHaveCount(1);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/keuangan/mutasi-bank?perusahaan=${run.company}`);
        await expect(page.locator('#konten')).toContainText(`TRX-${run.code}`);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        if (width >= 768) {
            await page.getByRole('button', { name: 'Catat Mutasi Bank' }).click();
            await expect(page.getByRole('dialog', { name: 'Catat Mutasi Bank' })).toBeVisible();
            await atRest(page);
            expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
        }
    });
}
