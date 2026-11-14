import { expect as base, test } from '@playwright/test';

import { auditPage } from '../localization-audit';
import { otherCashRun } from '../other-cash-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-054): PG-049 in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { title: 'Pengeluaran & Kas Lain', disburse: 'Catat Pengeluaran Kas', expense: 'Catat Beban', cashIn: 'Catat Kas Masuk Lain', tab: 'Kas masuk lain', empty: 'Belum ada pengeluaran kas.' }],
    ['en', { title: 'Disbursements and other cash', disburse: 'Record cash disbursement', expense: 'Record expense', cashIn: 'Record other cash-in', tab: 'Other cash-in', empty: 'No disbursement yet.' }],
] as const) {
    test(`shows PG-049 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await otherCashRun(page, `kas-lain-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/keuangan/kas-lain?perusahaan=${run.company}`);
        await expect(page.getByRole('heading', { name: texts.title, level: 1 })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.disburse, exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.expense, exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.cashIn, exact: true })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.tab })).toBeVisible();
        await expect(page.getByText(texts.empty)).toBeVisible();
        await auditPage(page, locale, 'PG-049');
    });
}
