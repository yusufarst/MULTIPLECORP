import { expect, test } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-10 (TASK-026; LOCALIZATION §3; DOD-16): PG-054 and PG-055 in both languages — keys of the catalogue, never
// hardcoded copy.

for (const [locale, texts] of [
    ['id', { clients: 'Klien', suppliers: 'Pemasok', createClient: 'Buat Klien', createSupplier: 'Buat Pemasok', search: 'Cari klien' }],
    ['en', { clients: 'Clients', suppliers: 'Suppliers', createClient: 'Create Client', createSupplier: 'Create Supplier', search: 'Search clients' }],
] as const) {
    test(`shows PG-054 and PG-055 in ${locale}`, async ({ page }) => {
        await ownerSignedIn(page, `pihak-${locale}-${String(Date.now())}`, locale);
        await numberingCompany(page, null);

        await page.goto('/data-master/klien');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.clients);
        await expect(page.getByRole('combobox', { name: texts.search })).toBeVisible();
        await expect(page.getByRole('button', { name: texts.createClient }).first()).toBeVisible();
        await expect(page.getByRole('navigation').getByRole('link', { name: texts.suppliers })).toBeVisible();

        await page.goto('/data-master/pemasok');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(texts.suppliers);
        await expect(page.getByRole('button', { name: texts.createSupplier }).first()).toBeVisible();
    });
}
