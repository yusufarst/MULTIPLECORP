import { expect as base, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { adjustmentRun } from '../adjustment-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-042; IP-09; IP-20): the reason reachable in its step and announced by its name, the units by their labels, the relation marker read as text.

test.describe.configure({ timeout: 240_000 });

test('names the units, the proof and the reason on PG-027, the relation marker as text to a Company-A-only Admin', async ({ page, browser }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await adjustmentRun(page, `sesuai-a11y-${String(Date.now())}`);

    await page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=kondisi`);
    const row = page.locator('[data-slot="adjustment-lot"]').filter({ hasText: run.lot }).first();
    await row.getByLabel(/Jumlah dari/).fill('1');
    await page.getByLabel('Bukti', { exact: true }).selectOption({ index: 1 });
    await page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Ubah Kondisi' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog.getByLabel('Alasan')).toBeFocused();

    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
    const admin = await context.newPage();
    await signedIn(admin, 'sesuai-a11y-admin', { plain: true });
    expect((await admin.request.post('/__fixtures/adjustments/plain', { data: { company: run.company } })).ok()).toBe(true);
    await admin.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=kondisi`);
    await expect(admin.locator('[data-slot="adjustment-lot"]').filter({ hasText: 'perusahaan lain' }).first()).toBeVisible();
    await context.close();
});
