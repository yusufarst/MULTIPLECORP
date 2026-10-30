import { expect, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

// TL-10 (TASK-049; LOCALIZATION §3, §3.4; DOD-16): PG-041–PG-043 in both languages — keys of the catalogue, never hardcoded copy —, the paper Indonesian whatever the interface language.

for (const [locale, texts] of [
    ['id', { list: 'Invoice', position: 'Posisi invoice', outstanding: 'Sisa tagihan', save: 'Simpan Draf', issue: 'Terbitkan Invoice', from: 'Diambil dari proyek', unbilled: 'Belum Ditagihkan' }],
    ['en', { list: 'Invoices', position: 'Invoice position', outstanding: 'Outstanding', save: 'Save Draft', issue: 'Issue Invoice', from: 'Taken from the project', unbilled: 'Not Yet Billed' }],
] as const) {
    test(`shows PG-041–PG-043 in ${locale}, the paper in Indonesian`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await invoiceRun(page, `invoice-${locale}-${String(Date.now())}`, { stage: 'issued', locale });

        await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
        await expect(page.getByText(texts.position, { exact: true })).toBeVisible();
        await expect(page.getByTestId('position')).toContainText(texts.outstanding);

        await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);
        await expect(page.getByText(texts.from, { exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.save })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.issue })).toBeVisible();
        const paper = page.getByRole('region', { name: /Invoice/ });
        await expect(paper).toContainText('DRAF — belum terbit');
        await expect(paper).toContainText('Subtotal');
        await expect(paper).toContainText('Total');
    });
}
