import { expect as base, test } from '@playwright/test';

import { completionRun } from '../completion-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-10 (TASK-055): PG-012 and its Force Complete section in both languages.

test.describe.configure({ timeout: 240_000 });

for (const [locale, texts] of [
    ['id', { title: 'Penyelesaian Proyek', conditions: 'Syarat penyelesaian', force: 'Selesaikan Paksa' }],
    ['en', { title: 'Project completion', conditions: 'Completion conditions', force: 'Force Complete' }],
] as const) {
    test(`shows PG-012 in ${locale}`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await completionRun(page, `selesai-${locale}-${String(Date.now())}`, false, locale);

        await page.goto(`/proyek/${run.project}/penyelesaian`);
        await expect(page.getByRole('heading', { name: texts.title, level: 1 })).toBeVisible();
        await expect(page.getByRole('heading', { name: texts.conditions, level: 2 })).toBeVisible();
        await expect(page.locator('[data-slot="completion-force"]').getByRole('button', { name: texts.force })).toBeVisible();
    });
}
