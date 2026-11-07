import { expect as base, test } from '@playwright/test';

import { stockRun } from '../stock-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-059; LOCALIZATION §3; DOD-16): PG-009 and its scan message in both languages, from the catalogue.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { title: 'Cari', field: 'Cari atau pindai kode', match: 'Kode Cocok', unknown: 'tidak dikenal' }],
    ['en', { title: 'Search', field: 'Search or scan a code', match: 'Code Match', unknown: 'is not recognized' }],
] as const) {
    test(`shows PG-009 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await stockRun(page, `cari-${locale}-${String(Date.now())}`, locale);

        await page.goto('/cari');
        await expect(page.getByRole('heading', { name: texts.title, exact: true })).toBeVisible();
        const field = page.locator('#konten').getByRole('textbox', { name: texts.field });
        await field.fill(run.sku);
        await expect(page.locator('[data-slot="search-exact"]').getByRole('heading', { name: texts.match })).toBeVisible();

        await field.fill('');
        await page.keyboard.type(`TIDAKADA${locale}${String(Date.now())}`, { delay: 5 });
        await page.keyboard.press('Enter');
        await expect(page.locator('[data-slot="scan-unknown"]')).toContainText(texts.unknown);
    });
}
