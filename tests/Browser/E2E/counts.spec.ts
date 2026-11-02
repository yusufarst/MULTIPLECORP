import { expect as base, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { adjustmentRun } from '../adjustment-fixtures';
import { applyWith, countLine, openCount } from '../count-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-043; J-INV-05; J-INV-04; CJ-05; INT-191–INT-193; INT-197–INT-200; MSG-30; MSG-41; UXS-39): counting on the phone and applying on the desktop, a stale count's "Hitung Ulang", a pending case and the Owner's resolution with its exact reversal, a surplus finding and its attribution, the candidates by PJ-07.

test.describe.configure({ timeout: 420_000 });

test('J-INV-05: counts on the phone from PG-019, names the shortage\'s lot and applies on the desktop (CJ-05; criteria 1, 2)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await adjustmentRun(page, `opname-${String(Date.now())}`);
    await page.setViewportSize({ width: 390, height: 844 });

    await page.goto(`/gudang/stok/${run.product}`);
    await page.locator('[data-slot="product-commands"]').getByRole('link', { name: 'Mulai Hitungan' }).click();
    await expect(page.locator('[data-slot="count-open"]')).toContainText(run.name);
    await openCount(page, run.product, run.rack);
    await countLine(page, '11');
    await expect(page.locator('[data-slot="count-variance"]').first()).toContainText('-1');

    await page.setViewportSize({ width: 1280, height: 900 });
    const row = page.locator('[data-slot="count-lot"]').filter({ hasText: run.lot }).first();
    await row.getByLabel(/Jumlah dari/).fill('1');
    await applyWith(page, 'Satu unit hilang dari rak');
    await expect(page.locator('[data-slot="count-detail"]')).toContainText('Diterapkan');
    await expect(page.locator('[data-slot="count-detail"]')).not.toContainText('Catat Baris');
});

test('marks a moved scope stale with MSG-30 and "Hitung Ulang", a surplus a finding the Owner attributes (ST-03; AX-07; criteria 1, 5, 6)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await adjustmentRun(page, `opname-basi-${String(Date.now())}`);

    await openCount(page, run.product, run.rack);
    await countLine(page, '12');
    const first = page.url();

    await openCount(page, run.product, run.rack);
    await countLine(page, '13');
    await expect(page.locator('[data-slot="count-surplus-note"]')).toContainText('belum ditambahkan ke stok');
    await applyWith(page, 'Ada satu unit lebih');
    await expect(page.locator('[data-slot="count-detail"]')).toContainText('Diterapkan');

    await page.goto(first);
    await applyWith(page, 'Hitungan pertama');
    await expect(page.locator('[data-slot="count-stale"]')).toContainText('kedaluwarsa');
    await page.locator('[data-slot="count-stale"]').getByRole('button', { name: 'Hitung Ulang' }).click();
    await expect(page.locator('[data-slot="count-line"]')).toBeVisible();

    await page.goto('/gudang/selisih-temuan');
    const finding = page.locator('[data-slot="finding-row"]').filter({ hasText: run.name }).first();
    await finding.getByLabel('Perusahaan pemilik').selectOption({ index: 1 });
    await finding.getByLabel('Dasar biaya bertanda').fill('1000');
    await finding.getByRole('button', { name: 'Tetapkan Surplus' }).click();
    await page.getByRole('alertdialog').getByLabel('Alasan').fill('Stok ditemukan saat opname');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Tetapkan Surplus' }).click();
    await expect(page.locator('#konten')).toContainText('Surplus ditetapkan');
    await expect(page.locator('[data-slot="finding-row"]').filter({ hasText: run.name })).toHaveCount(0);
});

test('records a shortage across companies as a pending case, the Owner resolves it, the reversal re-opens it; a Company-A-only Admin never sees the other company (CJ-05; SF-UNATTRIBUTED; PJ-07; UXS-39; criteria 3, 7, 9, 10)', async ({ page, browser }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await adjustmentRun(page, `opname-tunda-${String(Date.now())}`);

    await openCount(page, run.product, run.rack);
    await countLine(page, '11');
    await expect(page.locator('[data-slot="count-across"]')).toContainText('Selisih menunggu penetapan');
    await page.locator('[data-slot="count-split"]').getByLabel('Selisih menunggu penetapan').fill('1');
    await applyWith(page, 'Tidak jelas milik siapa');
    await expect(page.locator('[data-slot="count-detail"]')).toContainText('Diterapkan');

    await page.goto(`/gudang/stok/${run.product}`);
    await expect(page.locator('[data-slot="stock-pending"]')).toContainText('menunggu penetapan Owner');

    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
    const admin = await context.newPage();
    await signedIn(admin, 'opname-admin');
    expect((await admin.request.post('/__fixtures/counts/admin', { data: { company: run.company } })).ok()).toBe(true);
    await admin.goto('/gudang/selisih-temuan');
    const seen = admin.locator('[data-slot="case-row"]').filter({ hasText: run.name }).first();
    await expect(seen).toContainText('menunggu penetapan Owner');
    await expect(admin.locator('#konten')).not.toContainText(run.other_code);
    await expect(seen.getByRole('button', { name: 'Tetapkan Perusahaan' })).toHaveCount(0);
    await context.close();

    await page.goto('/gudang/selisih-temuan');
    const owned = page.locator('[data-slot="case-row"]').filter({ hasText: run.name }).first();
    await expect(owned.locator('[data-slot="case-exposures"]')).toContainText(run.other_code);
    await owned.locator('[data-slot="case-lot"]').filter({ hasText: run.other_code }).first().getByLabel(/Jumlah dari/).fill('1');
    await owned.getByRole('button', { name: 'Tetapkan Perusahaan' }).click();
    await page.getByRole('alertdialog').getByLabel('Alasan').fill('Keputusan Owner');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Tetapkan Perusahaan' }).click();
    await expect(page.locator('#konten')).toContainText('Selisih ditetapkan');

    const resolution = page.locator('[data-slot="resolution-row"]').filter({ hasText: run.name }).first();
    await resolution.getByRole('button', { name: 'Balikkan Penetapan' }).click();
    await page.getByRole('alertdialog').getByLabel('Alasan').fill('Penetapan keliru');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Balikkan Penetapan' }).click();
    await expect(page.locator('[data-slot="case-row"]').filter({ hasText: run.name }).first()).toBeVisible();
});

test('resolves a pending case by later evidence as a Company-A-only ADM+ holder, the company derived from its lots (PG-029; OD-10; criterion 8)', async ({ page, browser }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await adjustmentRun(page, `opname-bukti-${String(Date.now())}`);

    await openCount(page, run.product, run.rack);
    await countLine(page, '11');
    await page.locator('[data-slot="count-split"]').getByLabel('Selisih menunggu penetapan').fill('1');
    await applyWith(page, 'Tidak jelas milik siapa');
    await expect(page.locator('[data-slot="count-detail"]')).toContainText('Diterapkan');

    // A proof registered after the recognition.
    const later = await page.request.post('/__fixtures/adjustments', { data: {} });
    const { proof } = (await later.json()) as { proof: string };

    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
    const admin = await context.newPage();
    await signedIn(admin, 'opname-bukti');
    expect((await admin.request.post('/__fixtures/counts/admin', { data: { company: run.company } })).ok()).toBe(true);
    await admin.goto('/gudang/selisih-temuan');
    const row = admin.locator('[data-slot="case-row"]').filter({ hasText: run.name }).first();
    await row.locator('[data-slot="case-lot"]').filter({ hasText: run.lot }).first().getByLabel(/Jumlah dari/).fill('1');
    const field = row.getByLabel('Bukti', { exact: true });
    await field.selectOption({ value: (await field.locator('option', { hasText: proof }).getAttribute('value')) ?? '' });
    await row.getByRole('button', { name: 'Tetapkan dengan Bukti' }).click();
    await admin.getByRole('alertdialog').getByLabel('Alasan').fill('Bukti menunjukkan lot pemilik');
    await admin.getByRole('alertdialog').getByRole('button', { name: 'Tetapkan dengan Bukti' }).click();
    await expect(admin.locator('#konten')).toContainText('Selisih ditetapkan');
    await context.close();
});

test('opens a pending case through PG-027\'s adjustment across companies, never a named loss (J-INV-04; QS-22; criterion 13)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await adjustmentRun(page, `opname-sesuai-${String(Date.now())}`);

    await page.goto(`/gudang/penyesuaian?produk=${run.product}&aksi=penyesuaian`);
    await page.getByLabel('Jenis penyesuaian').selectOption({ label: 'Penyusutan' });
    await page.locator('[data-slot="adjustment-lots"]').getByRole('checkbox', { name: 'Lot unit ini tidak dapat dikenali dari bukti' }).check();
    await expect(page.locator('[data-slot="adjustment-pending-note"]')).toContainText('Selisih menunggu penetapan');
    await page.getByLabel(/Selisih menunggu penetapan di/).first().fill('1');
    await page.getByLabel('Bukti', { exact: true }).selectOption({ index: 1 });
    await page.locator('[data-slot="adjustment-form"]').getByRole('button', { name: 'Catat Penyesuaian Stok' }).click();
    await page.getByRole('alertdialog').getByLabel('Alasan').fill('Tidak jelas milik siapa');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Penyesuaian Stok' }).click();
    await page.waitForURL(`**/gudang/stok/${run.product}`);
    await expect(page.locator('[data-slot="stock-pending"]')).toContainText('menunggu penetapan Owner');
});
