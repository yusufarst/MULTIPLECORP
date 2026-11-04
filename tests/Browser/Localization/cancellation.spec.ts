import { expect as base, test } from '@playwright/test';

import { invoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-056): the cancellation dialog in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { more: 'Tindakan Lain', cancel: 'Batalkan Proyek', stays: 'stok bebas' }],
    ['en', { more: 'More actions', cancel: 'Cancel project', stays: 'free stock' }],
] as const) {
    test(`shows the cancellation dialog in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await invoiceRun(page, `batal-${locale}-${String(Date.now())}`, { locale });

        await page.goto(`/proyek/${run.project}`);
        await page.getByRole('button', { name: texts.more }).first().click();
        await page.getByRole('menuitem', { name: texts.cancel }).click();
        await expect(page.getByRole('alertdialog')).toContainText(texts.stays);
    });
}
