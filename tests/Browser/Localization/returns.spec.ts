import { expect as base, test } from '@playwright/test';

import { auditPage } from '../localization-audit';
import { returnRun } from '../return-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-044): PG-028 in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { title: 'Retur ke Pemasok', list: 'Kasus retur pemasok', lot: 'Lot yang diretur' }],
    ['en', { title: 'Supplier return', list: 'Supplier return cases', lot: 'Lot returned' }],
] as const) {
    test(`shows PG-028 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await returnRun(page, `retur-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/gudang/retur-pemasok?pembelian=${run.purchase}`);
        await expect(page.getByRole('heading', { name: texts.title, level: 1 })).toBeVisible();
        await expect(page.locator('[data-slot="return-list"]')).toContainText(texts.list);
        await expect(page.locator('[data-slot="return-form"]').getByLabel(texts.lot)).toBeVisible();
        await auditPage(page, locale, 'PG-028');
    });
}
