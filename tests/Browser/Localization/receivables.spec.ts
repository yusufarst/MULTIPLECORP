import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-050; LOCALIZATION §3; DOD-16): PG-044 and PG-043's receivable controls in both languages, keys of the catalogue and never hardcoded copy. DOC-13's paper stays Indonesian, asserted in tests/Feature/FIN/ReceivablesTest.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { title: 'Piutang', active: 'Aktif', unbilled: 'Belum Ditagihkan', disputed: 'Sengketa', billing: 'Penagihan', hold: 'Tandai Sengketa', writeOff: 'Hapuskan Piutang', more: 'Tindakan lain' }],
    ['en', { title: 'Receivables', active: 'Active', unbilled: 'Not Yet Billed', disputed: 'Disputed', billing: 'Billing', hold: 'Mark as Disputed', writeOff: 'Write Off Receivable', more: 'More Actions' }],
] as const) {
    test(`shows PG-044 and PG-043's controls in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await invoiceRun(page, `piutang-${locale}-${String(Date.now())}`, { stage: 'issued', billed: true, locale });

        await page.goto(`/keuangan/piutang?perusahaan=${run.company}`);
        await expect(page.getByRole('heading', { name: texts.title, exact: true })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.active })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.unbilled })).toBeVisible();
        await expect(page.getByRole('tab', { name: texts.disputed })).toBeVisible();

        await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
        await expect(page.getByText(texts.billing, { exact: true })).toBeVisible();
        await page.getByRole('button', { name: texts.more }).click();
        await expect(page.getByRole('menuitem', { name: texts.hold })).toBeVisible();
        await expect(page.getByRole('menuitem', { name: texts.writeOff })).toBeVisible();
    });
}
