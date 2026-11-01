import { expect as base, test } from '@playwright/test';

import { dispatchRun } from '../dispatch-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-041; LOCALIZATION §3; §3.4; DOD-16): PG-023 and PG-024 in both languages, keys of the catalogue and never hardcoded copy.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { list: 'Barang Keluar', page: 'Keluarkan Barang', quantity: 'Jumlah keluar', lots: 'Lot yang diambil', action: 'Catat Barang Keluar' }],
    ['en', { list: 'Dispatch', page: 'Dispatch Goods', quantity: 'Quantity out', lots: 'Lots taken', action: 'Record Dispatch' }],
] as const) {
    test(`shows PG-023 and PG-024 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await dispatchRun(page, `keluar-${locale}-${String(Date.now())}`, locale);

        await page.goto('/gudang/barang-keluar');
        await expect(page.getByRole('heading', { name: texts.list, exact: true })).toBeVisible();

        await page.goto(`/gudang/barang-keluar/${run.project}`);
        await expect(page.getByRole('heading', { name: texts.page, exact: true })).toBeVisible();
        const line = page.locator('[data-slot="dispatch-line"]:visible');
        await expect(line.getByLabel(texts.quantity)).toBeVisible();
        await expect(line.getByText(texts.lots, { exact: true })).toBeVisible();
        await expect(line.getByRole('button', { name: texts.action })).toBeVisible();
    });
}
