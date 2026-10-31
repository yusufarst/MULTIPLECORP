import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-051; LOCALIZATION §3; DOD-16): PG-045, PG-046 and PG-050 in both languages, keys of the catalogue and never hardcoded copy; a language switch keeps the unsent input. DOC-05's and DOC-07's paper stays Indonesian, asserted in tests/Feature/FIN/PaymentDocumentsTest.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { payments: 'Pembayaran', all: 'Semua', credit: 'Kredit', proof: 'Tanpa Bukti', record: 'Catat Pembayaran', method: 'Metode', bank: 'Mutasi Bank', lines: 'Baris Mutasi', action: 'Catat Mutasi Bank' }],
    ['en', { payments: 'Payments', all: 'All', credit: 'Credit', proof: 'No Proof', record: 'Record payment', method: 'Method', bank: 'Bank statement', lines: 'Statement Lines', action: 'Record Bank Statement Line' }],
] as const) {
    test(`shows PG-045, PG-046 and PG-050 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await invoiceRun(page, `bayar-${locale}-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, locale });

        await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
        await expect(page.getByRole('heading', { name: texts.payments, exact: true })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.all })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.credit })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.proof })).toBeVisible();

        await page.goto(`/keuangan/pembayaran/baru?invoice=${run.invoice ?? ''}`);
        await expect(page.getByRole('heading', { name: texts.record, exact: true })).toBeVisible();
        await expect(page.getByLabel(new RegExp(`^${texts.method}`))).toBeVisible();

        await page.goto(`/keuangan/mutasi-bank?perusahaan=${run.company}`);
        await expect(page.getByRole('heading', { name: texts.bank, exact: true })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.lines })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.action })).toBeVisible();
    });
}
