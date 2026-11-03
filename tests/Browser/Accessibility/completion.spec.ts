import { expect as base, test } from '@playwright/test';

import { completionRun } from '../completion-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-055; IP-08; IP-09; DESIGN_SYSTEM §15): the Force Complete's confirmation names its reason in a named dialog, "Kembali" closing it with nothing sent (NV-02).

test.describe.configure({ timeout: 240_000 });

test('names the Force Complete dialog\'s reason and closes it with nothing sent', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await completionRun(page, `paksa-a11y-${String(Date.now())}`, false);

    await page.goto(`/proyek/${run.project}/penyelesaian`);
    await page.locator('[data-slot="completion-force"]').getByRole('button', { name: 'Selesaikan Paksa' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog.getByLabel('Alasan')).toBeVisible();
    await dialog.getByRole('button', { name: 'Kembali', exact: true }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page.locator('[data-slot="completion-record"]')).toHaveCount(0);
});
