import { expect, test } from '@playwright/test';

import { correctionCases } from '../case-fixtures';

// TL-10 (TASK-031; LOCALIZATION §3; DOD-16): PG-040 in both languages — keys of the catalogue, never hardcoded copy.

for (const [locale, texts] of [
    ['id', { title: 'Kasus Koreksi', kind: 'Retur penjualan', residual: 'potongan yang belum terselesaikan', close: 'Tutup Kasus' }],
    ['en', { title: 'Correction cases', kind: 'Sales return', residual: 'an unresolved deduction', close: 'Close case' }],
] as const) {
    test(`shows PG-040 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const { company } = await correctionCases(page, `kasus-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/dokumen/kasus?perusahaan=${company}`);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.title);
        await expect(page.getByRole('region', { name: texts.kind })).toBeVisible();
        await expect(page.locator('#konten')).toContainText(texts.residual);
        await expect(page.getByRole('button', { name: texts.close })).toHaveCount(1);
    });
}
