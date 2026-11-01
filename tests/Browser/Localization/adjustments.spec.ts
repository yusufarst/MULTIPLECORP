import { expect as base, test } from '@playwright/test';

import { adjustmentRun } from '../adjustment-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-042): PG-027 in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { page: 'Penyesuaian & Kondisi', action: 'Ubah Kondisi', statement: 'Kerugian dicatat satu kali', adjust: 'Catat Penyesuaian Stok' }],
    ['en', { page: 'Adjustments and condition', action: 'Change condition', statement: 'The loss is recorded once', adjust: 'Record stock adjustment' }],
] as const) {
    test(`shows PG-027 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await adjustmentRun(page, `sesuai-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=kondisi`);
        await expect(page.getByRole('heading', { name: new RegExp(texts.page, 'i'), level: 1 })).toBeVisible();
        await expect(page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: new RegExp(texts.action, 'i') })).toBeVisible();

        await page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=penyesuaian`);
        await expect(page.locator('[data-slot="adjustment-form"]')).toContainText(texts.statement);
    });
}
