import { expect as base, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { stockRun } from '../stock-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-040; UXS-20; UXS-39; CJ-05; CJ-09; CM-28; IP-09; IP-15): PG-018–PG-020 with the Owner and a Company-A-only Admin, the pending line, "Balikkan" on PG-020 linked to its original, the phone's scan-or-search entry.

test.describe.configure({ timeout: 300_000 });

test('shows the four quantities with the formula, per product and per rack, the restock advice (annex 7; D-IA-17; BR-INV-10; criteria 1, 6)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await stockRun(page, `stok-${String(Date.now())}`);

    await page.goto(`/gudang/stok?cari=${encodeURIComponent(run.name)}`);
    await expect(page.getByRole('heading', { name: 'Stok', exact: true })).toBeVisible();
    await expect(page.locator('[data-slot="stock-formula"]')).toContainText('Tersedia');
    const row = page.getByRole('row').filter({ hasText: run.name });
    // The other company's opening 4 is in the pool: 14 on hand, 3 reserved, 2 unusable, 9 available.
    await expect(row).toContainText('14');
    await expect(row).toContainText('9');
    await expect(row.locator('[data-slot="stock-below"]')).toContainText('Di bawah minimum 50');
    await expect(row.getByRole('link', { name: 'Buat Pembelian' })).toHaveAttribute('href', `/pembelian/baru?produk=${run.product}`);

    await page.getByRole('button', { name: 'Per Rak' }).click();
    await expect(page.getByRole('row').filter({ hasText: run.rack }).first()).toBeVisible();
});

test('shows a lot in scope with its source and another company\'s by its marker alone to a Company-A-only Admin; nothing of that company in the history (UXS-20; CJ-09; PJ-01–PJ-04; criteria 2, 3, 7, 8)', async ({ browser, page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await stockRun(page, `stok-a-${String(Date.now())}`);

    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
    const admin = await context.newPage();
    await signedIn(admin, 'stok-admin', { plain: true });
    await admin.request.post('/__fixtures/stock/grant', { data: { company: run.company } });

    await admin.goto(`/gudang/stok/${run.product}`);
    const lots = admin.locator('[data-slot="product-lots"]');
    const own = lots.locator('[data-slot="product-lot"]').filter({ hasText: run.lot }).first();
    await expect(own).toContainText('Sumber');
    const foreign = lots.locator('[data-slot="product-lot"]').filter({ hasText: 'perusahaan lain' });
    await expect(foreign.first()).toBeVisible();
    await expect(foreign.first()).not.toContainText('Sumber');
    await expect(foreign.first().locator('a')).toHaveCount(0);
    await expect(admin.locator('#konten')).not.toContainText(run.other_name);
    await expect(admin.locator('#konten')).not.toContainText('Biaya tersisa');
    await expect(lots.getByText('Ambil Lebih Dulu')).toHaveCount(1);

    await admin.goto('/gudang/riwayat-stok');
    await expect(admin.getByRole('heading', { name: 'Riwayat Stok', exact: true })).toBeVisible();
    await expect(admin.locator('#konten')).not.toContainText(run.other_code);
    await expect(admin.getByRole('button', { name: 'Balikkan', exact: true })).toHaveCount(0);
    await context.close();
});

test('reverses a wrong condition change on PG-020 with its reason, both records linked, no second reversal offered (CJ-05; CM-28; IP-09; criteria 10, 15)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await stockRun(page, `stok-balik-${String(Date.now())}`);

    await page.goto(`/gudang/riwayat-stok?produk=${run.product}&jenis=CONDITION_CHANGE`);
    await page.getByRole('button', { name: 'Balikkan', exact: true }).first().click();
    const dialog = page.getByRole('alertdialog', { name: 'Balikkan pergerakan ini?' });
    const confirm = dialog.getByRole('button', { name: 'Balikkan', exact: true });
    await expect(confirm).toBeDisabled();
    await dialog.getByRole('textbox').last().fill('Kondisinya keliru dicatat');
    await confirm.click();
    await expect(page.getByText('Pergerakan dibalikkan.')).toBeVisible();

    await page.goto(`/gudang/riwayat-stok?produk=${run.product}`);
    await expect(page.locator('[data-slot="corrected-by"]').first()).toBeVisible();
    await expect(page.locator('[data-slot="corrects"]').first()).toBeVisible();
    await expect(page.locator('#konten')).toContainText('Kondisinya keliru dicatat');
    await page.goto(`/gudang/riwayat-stok?produk=${run.product}&jenis=CONDITION_CHANGE`);
    await expect(page.getByRole('button', { name: 'Balikkan', exact: true })).toHaveCount(0);
});

test('shows a pending quantity as its own line on PG-018 and PG-019 (UXS-39; PJ-07; criterion 5)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await stockRun(page, `stok-selisih-${String(Date.now())}`);
    const pending = await page.request.post('/__fixtures/stock/pending', { data: { company: run.company, product: run.product, lot: run.lot, rack: run.rack } });
    expect(pending.ok()).toBe(true);

    await page.goto(`/gudang/stok?cari=${encodeURIComponent(run.name)}`);
    await expect(page.locator('[data-slot="stock-pending"]').first()).toContainText('menunggu penetapan Owner');
    await page.goto(`/gudang/stok/${run.product}`);
    await expect(page.locator('[data-slot="stock-pending"]')).toContainText('menunggu penetapan Owner');
});

test('opens a product from a scan on the phone, its lots stacked (IP-15; NAV-018; PT-17)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await stockRun(page, `stok-hp-${String(Date.now())}`);

    await page.goto('/gudang/stok');
    await page.getByRole('textbox', { name: 'Pindai atau ketik kode' }).click();
    await page.keyboard.type(run.sku, { delay: 5 });
    await page.keyboard.press('Enter');
    await page.waitForURL(`**/gudang/stok/${run.product}`);
    await expect(page.locator('[data-slot="inventory-block"]')).toBeVisible();
    await expect(page.locator('[data-slot="product-lots"]')).toContainText(run.lot);
});
