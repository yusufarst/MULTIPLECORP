import { expect, test } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-10 (TASK-028; LOCALIZATION §3; DOD-16): PG-057 in both languages — keys of the catalogue, never hardcoded copy.

for (const [locale, texts] of [
    ['id', { title: 'Lokasi Rak', racks: 'Rak', minimums: 'Stok Minimum', create: 'Tambah Rak' }],
    ['en', { title: 'Rack locations', racks: 'Racks', minimums: 'Minimum Stock', create: 'Add Rack' }],
] as const) {
    test(`shows PG-057 in ${locale}`, async ({ page }) => {
        await ownerSignedIn(page, `lokasi-${locale}-${String(Date.now())}`, locale);
        await numberingCompany(page, null);

        await page.goto('/data-master/lokasi-rak');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.title);
        await expect(page.getByRole('heading', { name: texts.racks, exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: texts.minimums })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.create }).first()).toBeVisible();
    });
}
