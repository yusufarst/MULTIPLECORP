import { expect as base, test } from '@playwright/test';

import { deliveryRun } from '../delivery-fixtures';
import { auditPage } from '../localization-audit';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-045): PG-030 and PG-031 in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { list: 'Pengiriman', awaiting: 'Menunggu diterima', record: 'Catat Pengiriman', lines: 'Barang yang dikirim' }],
    ['en', { list: 'Deliveries', awaiting: 'Awaiting delivery', record: 'Record delivery', lines: 'Goods shipped' }],
] as const) {
    test(`shows PG-030 and PG-031 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await deliveryRun(page, `kirim-${locale}-${String(Date.now())}`, locale);

        await page.goto('/gudang/pengiriman');
        await expect(page.getByRole('heading', { name: texts.list, level: 1 })).toBeVisible();
        await expect(page.getByRole('link', { name: texts.awaiting })).toBeVisible();
        await auditPage(page, locale, 'PG-030');
        await page.goto(`/gudang/pengiriman/${run.shipment}`);
        await expect(page.getByRole('heading', { name: texts.record, level: 1 })).toBeVisible();
        await expect(page.locator('[data-slot="delivery-lines"]')).toContainText(texts.lines);
        await auditPage(page, locale, 'PG-031');
    });
}
