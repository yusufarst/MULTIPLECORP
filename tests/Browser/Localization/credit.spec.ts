import { expect as base, test } from '@playwright/test';

import { creditRun } from '../credit-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-053): PG-048 in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { title: 'Kredit Pelanggan', sources: 'Sumber kredit', refund: 'Kembalikan Dana', projects: 'Kredit per proyek' }],
    ['en', { title: 'Customer credit', sources: 'Credit sources', refund: 'Refund', projects: 'Credit by project' }],
] as const) {
    test(`shows PG-048 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await creditRun(page, `kredit-${locale}-${String(Date.now())}`, locale);

        await page.goto('/keuangan/kredit-pelanggan');
        await expect(page.getByRole('heading', { name: texts.title, level: 1 })).toBeVisible();
        const group = page.locator('[data-slot="credit-group"]').filter({ hasText: run.client_name });
        await expect(group.locator('[data-slot="credit-sources"]')).toContainText(texts.sources);
        await expect(group.locator('[data-slot="credit-projects"]')).toContainText(texts.projects);
        await expect(group.getByRole('button', { name: texts.refund, exact: true }).first()).toBeVisible();
    });
}
