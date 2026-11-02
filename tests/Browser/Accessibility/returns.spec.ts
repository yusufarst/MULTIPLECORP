import { expect as base, test } from '@playwright/test';

import { openReturn, returnRun } from '../return-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-044; IP-09; DESIGN_SYSTEM §15): the return's fields by their names, the reason reachable and named in its dialog, the dialog closed by "Kembali" with the input kept.

test.describe.configure({ timeout: 240_000 });

test('names PG-028\'s fields and the reason, and keeps the input when the reason step goes back', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await returnRun(page, `retur-a11y-${String(Date.now())}`);

    await openReturn(page, run);
    const place = page.locator('[data-slot="return-place"]').filter({ hasText: 'Layak pakai' }).first().getByLabel(/Jumlah dari/);
    await place.fill('1');
    await page.locator('[data-slot="return-form"]').getByRole('button', { name: 'Retur ke Pemasok', exact: true }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog.getByLabel('Alasan')).toBeVisible();
    await dialog.getByRole('button', { name: 'Kembali' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(place).toHaveValue('1');
});
