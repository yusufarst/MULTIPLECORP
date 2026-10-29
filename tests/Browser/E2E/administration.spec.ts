import { expect, test } from '@playwright/test';

import { administrationRun } from '../administration-fixtures';

// TL-09 (TASK-035; J-ADM-01; CJ-20's checklist and uploads; CJ-17's N/A eligibility; criteria 1, 3, 5, 6, 13): in PG-011's
// "Kesiapan dokumen" the checklist with the default invoice item and a required SPK, missing first; a client-original
// requirement added and satisfied by linking the client's uploaded SPK; the Owner making the SPK waiver-eligible and
// marking it *Tidak Berlaku* with its reason; PG-039 listing what stays open with its filters in the address; on the
// phone what is missing with its upload.

test('builds a project\'s checklist, links the client\'s original and marks an eligible item Tidak Berlaku (J-ADM-01; criteria 1, 3, 5, 6)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await administrationRun(page, `administrasi-${String(Date.now())}`);
    const content = page.locator('#konten');

    await page.goto(`/proyek/${run.project}`);
    const checklist = page.getByRole('list', { name: 'Persyaratan administrasi' });
    await expect(checklist.getByRole('listitem')).toHaveCount(3);
    await expect(checklist).toContainText('Invoice proyek');
    await expect(checklist).toContainText('Belum Ada');
    await expect(content).not.toContainText(/%|dari \d+ terpenuhi/);

    await page.getByRole('button', { name: 'Tambah Persyaratan' }).click();
    const add = page.getByRole('alertdialog');
    await add.getByLabel(/^Jenis persyaratan/).selectOption('EXTERNAL_DOCUMENT');
    await add.getByLabel(/^Jenis dokumen pihak luar/).selectOption('CLIENT_SPK');
    await add.getByLabel(/^Uraian/).fill('SPK asli dari sekolah');
    await add.getByLabel(/^Cara memenuhi/).selectOption('UPLOADED');
    await add.getByText('Harus asli dari klien').click();
    await add.getByRole('button', { name: 'Tambah Persyaratan' }).click();
    await expect(content.getByText('Persyaratan ditambahkan.')).toBeVisible();
    await expect(checklist.getByRole('listitem')).toHaveCount(4);

    const original = checklist.getByRole('listitem').filter({ hasText: 'SPK asli dari sekolah' });
    await expect(original).toContainText('Harus asli dari klien');
    await original.getByRole('button', { name: 'Tautkan Dokumen' }).click();
    const link = page.getByRole('alertdialog');
    await expect(link.getByRole('combobox')).toContainText(run.issuer);
    await link.getByRole('button', { name: 'Tautkan Dokumen' }).click();
    await expect(content.getByText('Dokumen ditautkan.')).toBeVisible();
    await expect(original).toContainText('Terpenuhi');

    const spk = checklist.getByRole('listitem').filter({ hasText: 'SPK perusahaan untuk sekolah' });
    await expect(spk.getByRole('button', { name: 'Tandai Tidak Berlaku' })).toHaveCount(0);
    await spk.getByRole('button', { name: 'Izinkan Tidak Berlaku' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Izinkan Tidak Berlaku' }).click();
    await expect(content.getByText('Kelayakan Tidak Berlaku disimpan.')).toBeVisible();

    await spk.getByRole('button', { name: 'Tandai Tidak Berlaku' }).click();
    const waive = page.getByRole('alertdialog');
    await expect(waive.getByRole('button', { name: 'Tandai Tidak Berlaku' })).toBeDisabled();
    await waive.getByRole('textbox').last().fill('Sekolah tidak meminta SPK perusahaan');
    await waive.getByRole('button', { name: 'Tandai Tidak Berlaku' }).click();
    await expect(content.getByText('Persyaratan ditandai Tidak Berlaku.')).toBeVisible();
    await expect(spk).toContainText('Tidak Berlaku');
});

test('lists what stays open on PG-039 with its filters in the address, returning with reload (ROUTE-039; NAV-039; IP-18; criterion 13)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await administrationRun(page, `administrasi-daftar-${String(Date.now())}`);

    await page.goto(`/dokumen/administrasi?cabang=${run.company}`);
    await expect(page.getByRole('heading', { name: 'Dokumen Administrasi' })).toBeVisible();
    const table = page.getByRole('table', { name: 'Dokumen Administrasi' });
    await expect(table.getByRole('row').filter({ hasText: run.number })).toHaveCount(3);

    await page.getByLabel(/^Status/).selectOption('belum-ada');
    await expect(page).toHaveURL(/status=belum-ada/);
    await page.getByRole('tab', { name: 'Semua' }).click();
    await expect(page).toHaveURL(/tab=semua/);
    await page.reload();
    await expect(page.getByLabel(/^Status/)).toHaveValue('belum-ada');
    await expect(page.getByRole('tab', { name: 'Semua' })).toHaveAttribute('aria-selected', 'true');
    await expect(table.getByRole('row').filter({ hasText: run.number })).toHaveCount(3);
});

test('shows what is missing on the phone with its upload (J-ADM-01; IP-16)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const run = await administrationRun(page, `administrasi-ponsel-${String(Date.now())}`);

    await page.goto(`/proyek/${run.project}`);
    const checklist = page.getByRole('list', { name: 'Persyaratan administrasi' });
    await expect(checklist.getByRole('listitem').first()).toContainText('Belum Ada');
    await expect(checklist).toContainText('Belum ada dokumen terbit dengan berkas siap.');
    const letter = checklist.getByRole('listitem').filter({ hasText: 'Surat pesanan dari sekolah' });
    await expect(letter).toContainText('Belum ada unggahan yang sah.');
    await expect(letter.getByRole('link', { name: 'Unggah bukti' })).toHaveAttribute('href', '/dokumen/bukti');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});
