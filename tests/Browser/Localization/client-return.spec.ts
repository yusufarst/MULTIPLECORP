import { expect as base, test } from '@playwright/test';

import { clientReturnRun } from '../client-return-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-048): PG-034 in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { title: 'Retur dari Klien', lines: 'Barang yang diterima klien', record: 'Catat Retur dari Klien', none: 'Belum ada retur dari klien pada proyek ini.' }],
    ['en', { title: 'Client return', lines: 'Goods the client received', record: 'Record Client Return', none: 'No client return on this project yet.' }],
] as const) {
    test(`shows PG-034 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await clientReturnRun(page, `retur-${locale}-${String(Date.now())}`, locale);

        await page.goto(`/proyek/${run.project_id}/retur`);
        await expect(page.getByRole('heading', { name: texts.title, level: 1 })).toBeVisible();
        await expect(page.locator('[data-slot="return-lines"]')).toContainText(texts.lines);
        await expect(page.locator('[data-slot="return-form"]').getByRole('button', { name: texts.record, exact: true })).toBeVisible();
        await expect(page.locator('[data-slot="return-cases"]')).toContainText(texts.none);
    });
}
