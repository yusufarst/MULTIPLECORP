import { expect as base, type Locator, type Page, test } from '@playwright/test';

import { uniquePdf } from '../evidence-fixtures';
import { adminSignOff, batchOf, importRun, type ImportRun, PASSWORD, prepareCompany, preparerSignedIn, source, work } from '../import-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-057; J-MIG-01; UXS-19; INT-272–INT-275; ADMIN_FLOW §13): at the desk, "Siapkan Batch" from a synthetic CSV, "Jalankan Uji Coba" to *Uji Coba Lolos*, the
// Owner's and the Admin's sign-off to *Disetujui Bersama*, "Jalankan Impor" behind MSG-39 and the step-up to *Selesai Diimpor*; a malformed row surfaced with its number and
// reason; and an Admin's row whose committed twin lies outside scope listed under *Tidak Valid* with MSG-18 alone.

test.describe.configure({ timeout: 360_000 });

async function prepare(page: Page, run: ImportRun | (Omit<ImportRun, 'company'> & { company?: string }), kind: string, rows: Record<string, string>[], name: string): Promise<Locator> {
    await page.goto('/data-master/impor');
    await page.getByRole('button', { name: 'Siapkan Batch', exact: true }).first().click();
    const sheet = page.getByRole('dialog', { name: 'Siapkan Batch' });
    await sheet.getByLabel('Jenis data').selectOption({ value: kind });
    await sheet.getByLabel('Tanggal batas').fill(run.cutoff);
    await sheet.getByRole('checkbox', { name: new RegExp(`^${run.code} · `) }).check();
    await sheet.getByLabel('File sumber').setInputFiles({ name, mimeType: 'text/csv', buffer: source(run, rows) });
    await expect(sheet.getByText(name)).toBeVisible();
    await sheet.getByRole('button', { name: 'Siapkan Batch', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Batch disiapkan.');

    return page.locator('[data-slot="import-batch"]');
}

test('J-MIG-01: prepares a batch, dry-runs it, signs it off with the Admin and imports it behind the step-up (WF-MIG-01; INT-272–INT-275)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await importRun(page, `impor-${String(Date.now())}`);
    const batch = await prepare(page, run, 'LOCATION', [{ id: 'L1', kode: `RAK-${run.code}-1`, nama: 'Rak satu' }, { id: 'L2', kode: `RAK-${run.code}-2`, nama: 'Rak dua' }], 'lokasi.csv');
    await expect(batch.locator('[data-status="import_batch.PREPARED"]')).toBeVisible();

    await batch.getByRole('button', { name: 'Jalankan Uji Coba', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Uji coba dijalankan.');
    const id = batchOf(page);
    await work(page, id);
    await expect(batch.locator('[data-status="import_batch.DRY_RUN_PASSED"]')).toBeVisible();

    await batch.getByRole('button', { name: 'Setujui Hasil Uji Coba' }).click();
    const sheet = page.getByRole('dialog', { name: 'Setujui Hasil Uji Coba' });
    await sheet.getByLabel('Bukti persetujuan').setInputFiles({ name: 'persetujuan.pdf', mimeType: 'application/pdf', buffer: uniquePdf() });
    await expect(sheet.getByText('persetujuan.pdf')).toBeVisible();
    await sheet.getByRole('button', { name: 'Setujui Hasil Uji Coba' }).click();
    await expect(page.locator('#konten')).toContainText('Persetujuan dicatat.');
    await adminSignOff(page, id);
    await page.reload();
    await expect(batch.locator('[data-status="import_batch.SIGNED_OFF"]')).toBeVisible();

    await batch.getByRole('button', { name: 'Jalankan Impor' }).click();
    const confirm = page.getByRole('alertdialog', { name: 'Jalankan Impor' });
    await expect(confirm).toContainText('seed penomoran');
    await confirm.getByRole('button', { name: 'Jalankan Impor' }).click();
    const stepUp = page.getByRole('alertdialog', { name: 'Konfirmasi Kata Sandi' });
    await stepUp.getByLabel('Kata sandi').fill(PASSWORD);
    await stepUp.getByRole('button', { name: 'Lanjutkan' }).click();
    await expect(page.locator('#konten')).toContainText('Impor dijalankan.');
    await work(page, id);
    await expect(batch.locator('[data-status="import_batch.COMMITTED"]')).toBeVisible();
    await expect(batch.locator('[data-slot="import-counts"]')).toContainText('Terimpor');
});

test('J-MIG-01: surfaces a malformed row with its number and reason and keeps the batch from passing (WF-MIG-01; FL-06)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await importRun(page, `impor-salah-${String(Date.now())}`);
    const batch = await prepare(page, run, 'LOCATION', [{ id: 'L1', kode: `RAK-${run.code}-1`, nama: 'Rak satu' }, { id: 'L2', kode: '', nama: 'Tanpa kode' }], 'salah.csv');
    const problems = batch.locator('[data-slot="import-problems"]');

    await expect(problems).toContainText('Baris 3');
    await expect(problems).toContainText('kode: wajib diisi');
    await batch.getByRole('button', { name: 'Jalankan Uji Coba', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Uji coba dijalankan.');
    await work(page, batchOf(page));
    await expect(batch.locator('[data-status="import_batch.PREPARED"]')).toBeVisible();
    await expect(batch.getByRole('button', { name: 'Jalankan Uji Coba Lagi' })).toBeVisible();
});

test('UXS-19: lists a row whose committed twin lies outside the preparer\'s scope under Tidak Valid with MSG-18 alone (DP-18)', async ({ page, browser }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await importRun(page, `impor-kembar-${String(Date.now())}`);
    const identity = `S-${String(Date.now())}`;
    await page.request.post('/__fixtures/impor/kembar', { data: { identity } });

    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const admin = await context.newPage();
    const preparer = await preparerSignedIn(admin, `penyiap-${String(Date.now())}`);
    const scoped = await prepareCompany(page, run.company, preparer);
    const batch = await prepare(admin, scoped, 'OPENING_STOCK', [{ id: identity, perusahaan: scoped.code, produk: 'TIDAK-ADA', lokasi: 'TIDAK-ADA', jumlah: '1', biaya: '100' }], 'stok.csv');
    const problems = batch.locator('[data-slot="import-problems"]');

    await expect(problems).toContainText('Tidak dapat diimpor — perlu tinjauan Owner.');
    await expect(problems).toContainText('Tidak Valid');
    await expect(problems).not.toContainText('Duplikat');
    await expect(problems).not.toContainText('sudah terimpor');
    await context.close();
});
