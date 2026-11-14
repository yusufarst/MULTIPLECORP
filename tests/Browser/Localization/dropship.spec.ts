import { expect as base, test } from '@playwright/test';

import { dropshipRun } from '../dropship-fixtures';
import { auditPage } from '../localization-audit';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-046): PG-032 in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { title: 'Kirim Langsung', lines: 'Baris kirim langsung', confirm: 'Konfirmasi Kirim Langsung', none: 'Belum ada konfirmasi kirim langsung untuk proyek ini.' }],
    ['en', { title: 'Direct shipment',lines: 'Direct shipment lines', confirm: 'Confirm Direct Shipment', none: 'No direct shipment confirmed for this project yet.' }],
] as const) {
    test(`shows PG-032 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await dropshipRun(page, `langsung-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/proyek/${run.project}/kirim-langsung`);
        await expect(page.getByRole('heading', { name: texts.title, level: 1 })).toBeVisible();
        await expect(page.locator('[data-slot="dropship-lines"]')).toContainText(texts.lines);
        await expect(page.locator('[data-slot="dropship-form"]').getByRole('button', { name: texts.confirm, exact: true })).toBeVisible();
        await expect(page.locator('[data-slot="dropship-confirmations"]')).toContainText(texts.none);
        await auditPage(page, locale, 'PG-032');
    });
}
