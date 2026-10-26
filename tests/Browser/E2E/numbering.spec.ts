import { expect, test } from '@playwright/test';

import { clearGoLive, numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-09 (TASK-021; J-MD-01's numbering part; UXS-30, UXS-32; ADMIN_FLOW §12): the Owner's path on PG-061 against its
// real routes — go-live not recorded and the start not offered, a scheme added, "Mulai Penomoran" through its
// confirmation with the vouching statements and their one required tick, and the raise after a restore whose
// confirmation lists each counter with its new next number. Synthetic accounts and a synthetic company from the
// fixtures (routes/fixtures.php); go-live, whose store is TASK-077's, armed by the run.

test.afterEach(async ({ page }) => {
    await clearGoLive(page);
});

test('runs PG-061: no start before go-live, a scheme, the start with its statements, and the raise after a restore (J-MD-01; UXS-32)', async ({ page }) => {
    const run = `pen-${String(Date.now())}`;
    await ownerSignedIn(page, run);
    const company = await numberingCompany(page, null);
    const year = String(new Date(Date.now() + 7 * 3_600_000).getUTCFullYear());

    await page.goto(`/pengaturan/penomoran?perusahaan=${company}&jenis=project`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Penomoran Dokumen');
    await expect(page.getByText('Go-live belum dicatat. Penomoran dimulai setelah go-live dicatat saat peralihan sistem.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Mulai Penomoran' })).toHaveCount(0);

    // A scheme, ROUTE-136: it creates no counter.
    await page.getByRole('button', { name: 'Tambah Format Nomor' }).click();
    await page.getByLabel('Templat nomor').fill('UJI/PRJ/{tahun}/{nomor:4}');
    await expect(page.getByText(`Contoh: UJI/PRJ/${year}/0001`)).toBeVisible();
    await page.getByRole('button', { name: 'Simpan Format Nomor' }).click();
    await expect(page.getByText('Format nomor Proyek disimpan.')).toBeVisible();
    await expect(page.getByText('Belum ada penghitung.')).toBeVisible();

    // Go-live recorded at the cutover: the start is offered, one field per period of today.
    await numberingCompany(page, '2025-01-01');
    await page.goto(`/pengaturan/penomoran?perusahaan=${company}&jenis=project`);
    await page.getByLabel(`Nomor pertama periode ${year}`).fill('148');
    await page.getByRole('button', { name: 'Mulai Penomoran' }).click();

    const dialog = page.getByRole('alertdialog');
    await expect(dialog.getByText(`Periode ${year} → nomor pertama 148`)).toBeVisible();
    await expect(dialog.getByText('Mulai hari ini, Proyek tidak lagi dinomori di luar sistem ini.')).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Mulai Penomoran' })).toBeDisabled();
    await dialog.getByRole('checkbox').check();
    await dialog.getByRole('button', { name: 'Mulai Penomoran' }).click();
    await expect(page.getByText('Penomoran Proyek dimulai.')).toBeVisible();
    await expect(page.getByRole('cell', { name: '148' })).toBeVisible();
    await expect(page.getByText(/Batas awal penomoran: \d{4}-\d{2}-\d{2}/)).toBeVisible();

    // The raise after a restore: its confirmation lists each counter with its new next number.
    await page.getByRole('button', { name: 'Naikkan Penghitung Setelah Pemulihan' }).click();
    await page.getByLabel('Referensi pemulihan').fill('Pemulihan uji');
    await page.getByLabel('Titik pemulihan').fill('2026-10-01T08:00');
    const raise = page.locator('form').filter({ has: page.getByLabel('Referensi pemulihan') });
    await raise.getByLabel(/^Periode/).fill(year);
    await raise.getByLabel(/^Nomor tertinggi pada kertas/).fill('160');
    await page.getByRole('button', { name: 'Naikkan Penghitung Setelah Pemulihan' }).click();
    await expect(page.getByRole('alertdialog').getByText(`Periode ${year} → nomor berikutnya 161`)).toBeVisible();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Naikkan Penghitung Setelah Pemulihan' }).click();
    await expect(page.getByText('Penghitung Proyek dinaikkan.')).toBeVisible();
    await expect(page.getByRole('cell', { name: '161' })).toBeVisible();
});
