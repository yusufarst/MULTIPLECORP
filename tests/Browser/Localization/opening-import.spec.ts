import { expect as base, test } from '@playwright/test';

import { importRun } from '../import-fixtures';
import { auditPage } from '../localization-audit';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-057; LOCALIZATION §3.4): PG-058 and its preparation sheet in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { title: 'Impor Data Awal', prepare: 'Siapkan Batch', kind: 'Jenis data', stock: 'Stok awal' }],
    ['en', { title: 'Opening import', prepare: 'Prepare Batch', kind: 'Kind of data', stock: 'Opening stock' }],
] as const) {
    test(`shows PG-058 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await importRun(page, `impor-${locale}-${String(Date.now())}`, locale);

        await page.goto('/data-master/impor');
        await expect(page.getByRole('heading', { name: texts.title, level: 1 })).toBeVisible();
        await auditPage(page, locale, 'PG-058');
        await page.getByRole('button', { name: texts.prepare, exact: true }).first().click();
        const sheet = page.getByRole('dialog', { name: texts.prepare });
        await expect(sheet.getByLabel(texts.kind)).toContainText(texts.stock);
        await auditPage(page, locale, 'PG-058 with the preparation sheet');
    });
}
