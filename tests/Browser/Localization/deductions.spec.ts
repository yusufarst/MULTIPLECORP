import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-052; LOCALIZATION §3; DOD-16): PG-047 in both languages, keys of the catalogue and never hardcoded copy.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { title: 'Potongan', waiting: 'Klaim yang menunggu bukti', record: 'Catat Potongan', remaining: 'Sisa Potongan', all: 'Semua', line: 'Tanpa bukti tidak ada potongan' }],
    ['en', { title: 'Deductions', waiting: 'Claims waiting for evidence', record: 'Record deduction', remaining: 'Remaining deductions', all: 'All', line: 'Without evidence there is no deduction' }],
] as const) {
    test(`shows PG-047 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await invoiceRun(page, `potong-${locale}-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '900000', apply: true, claim: '30000', proven: '30000', locale });

        await page.goto(`/keuangan/potongan?perusahaan=${run.company}`);
        await expect(page.getByRole('heading', { name: texts.title, exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: texts.waiting, exact: true })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.all })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.remaining })).toBeVisible();

        await page.getByTestId('deduction-waiting').getByRole('button', { name: texts.record }).click();
        await expect(page.getByRole('dialog', { name: texts.record })).toContainText(texts.line);
    });
}
