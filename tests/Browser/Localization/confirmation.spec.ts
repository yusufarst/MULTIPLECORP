import { expect, test } from '@playwright/test';

import { confirmationRun } from '../confirmation-fixtures';

// TL-10 (TASK-034; LOCALIZATION §3; DOD-16): PG-011's *Item dan pemenuhan* in both languages — keys of the catalogue,
// never hardcoded copy; the business data stays as entered.

for (const [locale, texts] of [
    ['id', { title: 'Item dan pemenuhan', lines: 'Baris terkonfirmasi', kind: 'Dikonfirmasi tanpa penawaran', warehouse: 'Dari gudang', cancel: 'Batalkan Sisa', replan: 'Ubah Cara Pemenuhan' }],
    ['en', { title: 'Items and fulfilment', lines: 'Confirmed lines', kind: 'Confirmed without a quotation', warehouse: 'From the warehouse', cancel: 'Cancel Remainder', replan: 'Change Fulfilment Mode' }],
] as const) {
    test(`shows PG-011's confirmation and fulfilment in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await confirmationRun(page, `konfirmasi-${locale}-${String(Date.now())}`, { confirmed: true, locale });

        await page.goto(`/proyek/${run.project}`);
        await expect(page.getByRole('heading', { name: texts.title })).toBeVisible();
        await expect(page.locator('#konten')).toContainText(texts.kind);
        const lines = page.getByRole('list', { name: texts.lines });
        await expect(lines.locator('[data-line="1"]')).toContainText(texts.warehouse);
        await expect(lines.locator('[data-line="1"]')).toContainText(run.goods);
        await expect(lines.locator('[data-line="1"]').getByRole('button', { name: texts.cancel })).toBeVisible();
        await expect(lines.locator('[data-line="1"]').getByRole('button', { name: texts.replan })).toBeVisible();
    });
}
