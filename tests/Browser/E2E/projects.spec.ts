import { expect, type Page, test } from '@playwright/test';

import { projectRun, type ProjectRun } from '../project-fixtures';

// TL-09 (TASK-032; J-PRJ-01; CJ-01's project steps; CJ-08's SIPLAH project; UXS-28; criteria 1, 3, 6, 8, 10): "Buat
// Proyek" from PG-010 with the company, the client, its unit and PIC and a line, landing on PG-011 with the number drawn
// and the journey; "Ubah proyek" from *Tindakan Lain*; the row on PG-010 with its stage once the summary arrives; a SIPLAH
// project's values as entered; "Buang Draf" after its confirmation; a creation in a period whose numbering has not
// started refused with the Owner's path, the input kept; and the same journey on the phone.

const UUID = /\/proyek\/[0-9a-f-]{36}$/;

async function fillParties(page: Page, run: ProjectRun, title: string): Promise<void> {
    await page.getByLabel(/^Perusahaan/).selectOption(run.company);
    await page.getByRole('combobox', { name: 'Klien' }).fill(run.client);
    await page.getByRole('option', { name: run.client }).click();
    await page.getByLabel(/^Unit/).selectOption({ label: run.unit });
    await page.getByLabel(/^PIC/).selectOption({ index: 1 });
    await page.getByLabel(/^Judul proyek/).fill(title);
}

async function addLine(page: Page, product: string, quantity: string): Promise<void> {
    await page.getByRole('combobox', { name: 'Produk/Jasa' }).last().fill(product);
    await page.getByRole('option', { name: product }).click();
    await expect(page.getByRole('combobox', { name: 'Satuan' }).first()).toBeEnabled();
    await page.getByRole('textbox', { name: 'Jumlah' }).first().fill(quantity);
}

test('creates a project with its line, amends it and finds it on PG-010 with its stage (J-PRJ-01; CJ-01; criteria 1, 8)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await projectRun(page, `proyek-${String(Date.now())}`);

    await page.goto('/proyek');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Proyek');
    await page.getByRole('link', { name: 'Buat Proyek' }).first().click();
    await page.waitForURL('**/proyek/baru');
    await fillParties(page, run, 'Pengadaan buku perpustakaan');
    await addLine(page, run.goods, '12');
    await page.getByRole('button', { name: 'Buat Proyek' }).click();

    await page.waitForURL(UUID);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pengadaan buku perpustakaan');
    await expect(page.getByText('Proyek dibuat dan nomornya ditetapkan.')).toBeVisible();
    await expect(page.locator('#konten').getByText(/UJI\/PRJ\/\d{4}\/0001/).first()).toBeVisible();
    await expect(page.getByRole('list', { name: 'Tahap proyek' })).toContainText('Kesepakatan');
    await expect(page.locator('#konten')).toContainText(run.goods);
    await expect(page.locator('#konten')).toContainText('Proyek dibuat');

    await page.getByRole('button', { name: 'Tindakan Lain' }).click();
    await page.getByRole('menuitem', { name: 'Ubah proyek' }).click();
    await page.waitForURL('**/ubah');
    await page.getByLabel(/^Judul proyek/).fill('Pengadaan buku perpustakaan 2026');
    await page.getByRole('button', { name: 'Simpan Perubahan' }).click();
    await page.waitForURL(UUID);
    await expect(page.getByText('Perubahan proyek disimpan.')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pengadaan buku perpustakaan 2026');

    await page.goto('/proyek');
    const row = page.getByRole('row').filter({ hasText: 'Pengadaan buku perpustakaan 2026' });
    await expect(row).toContainText(run.client);
    await expect(row).toContainText('Kesepakatan');
});

test('records a SIPLAH project\'s values as entered, computing none (CJ-08; criterion 3; BR-FIN-10)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await projectRun(page, `siplah-${String(Date.now())}`);

    await page.goto('/proyek/baru');
    await fillParties(page, run, 'Pengadaan SIPLAH');
    await page.getByRole('radio', { name: 'SIPLAH' }).click();
    await page.getByLabel(/^Referensi SIPLAH/).fill('SPL-2026-0077');
    await page.getByLabel(/^Nilai SIPLAH/).fill('12500000');
    await addLine(page, run.service, '1');
    await page.getByRole('button', { name: 'Buat Proyek' }).click();

    await page.waitForURL(UUID);
    await expect(page.locator('#konten')).toContainText('Data SIPLAH');
    await expect(page.locator('#konten')).toContainText('SPL-2026-0077');
    await expect(page.locator('#konten')).toContainText('12.500.000');
    await expect(page.locator('#konten')).toContainText('tidak dihitung dan tidak memindahkan uang');
});

test('discards a draft after its confirmation, its number retired (criterion 6; L-37; IP-08)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await projectRun(page, `draf-${String(Date.now())}`, { project: true, draft: true });

    await page.goto(`/proyek/${run.project ?? ''}`);
    await expect(page.locator('#konten')).toContainText('Lengkapi unit dan PIC klien');
    await page.getByRole('button', { name: 'Tindakan Lain' }).click();
    await page.getByRole('menuitem', { name: 'Buang Draf' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Buang Draf?' });
    await expect(dialog).toContainText(`Nomor ${run.number ?? ''} dihentikan`);
    await dialog.getByRole('button', { name: 'Buang Draf' }).click();

    await page.waitForURL('**/proyek');
    await expect(page.getByText(`Draf ${run.number ?? ''} dibuang.`)).toBeVisible();
});

test('refuses a creation in a period whose numbering has not started with the Owner\'s path, the input kept (UXS-28; C-10; criterion 1)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await projectRun(page, `belum-${String(Date.now())}`, { numbered: false });

    await page.goto('/proyek/baru');
    await fillParties(page, run, 'Proyek tanpa penomoran');
    await page.getByRole('button', { name: 'Buat Proyek' }).click();

    await expect(page.getByText(/belum dimulai/)).toBeVisible();
    await expect(page.getByRole('link', { name: 'Mulai Penomoran' })).toHaveAttribute('href', new RegExp(`/pengaturan/penomoran\\?perusahaan=${run.company}`));
    await expect(page.getByLabel(/^Judul proyek/)).toHaveValue('Proyek tanpa penomoran');
    expect(page.url()).toContain('/proyek/baru');
});

test('creates and reads a project on the phone — the header, its next step and the track first (J-PRJ-01 on the phone; NAV-011)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await projectRun(page, `ponsel-${String(Date.now())}`);

    await page.goto('/proyek/baru');
    await fillParties(page, run, 'Proyek dari ponsel');
    await page.getByRole('button', { name: 'Buat Proyek' }).click();
    await page.waitForURL(UUID);

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Proyek dari ponsel');
    await expect(page.getByRole('list', { name: 'Tahap proyek' })).toBeVisible();
    await expect(page.locator('#konten')).toContainText('Proyek ini belum memiliki baris.');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});
