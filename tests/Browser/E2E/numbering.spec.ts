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

test('keeps a lost answer open for "Coba Lagi" under its identifier, and asks before unsent input is lost (IP-04; INT-009)', async ({ page }) => {
    const run = `pen-${String(Date.now())}`;
    await ownerSignedIn(page, run);
    const company = await numberingCompany(page, null);

    await page.goto(`/pengaturan/penomoran?perusahaan=${company}&jenis=project`);
    await page.getByRole('button', { name: 'Tambah Format Nomor' }).click();
    await page.getByLabel('Templat nomor').fill('UJI/PRJ/{tahun}/{nomor:4}');

    // INT-009: another type with unsent input asks MSG-35 first; "Kembali" keeps the input and the page.
    await page.getByLabel('Jenis nomor').selectOption('doc-01');
    const unsent = page.getByRole('alertdialog');
    await expect(unsent.getByText('Isian yang belum dikirim akan hilang. Tetap tinggalkan halaman?')).toBeVisible();
    await unsent.getByRole('button', { name: 'Kembali' }).click();
    await expect(page).toHaveURL(/jenis=project/);
    await expect(page.getByLabel('Templat nomor')).toHaveValue('UJI/PRJ/{tahun}/{nomor:4}');

    // IP-04: the answer lost on the way leaves MSG-07 with "Coba Lagi", which sends the same `command_id`.
    const sent: string[] = [];
    const saving = (url: URL) => url.pathname === '/pengaturan/penomoran/project';
    await page.route(saving, async (route) => {
        sent.push((route.request().postDataJSON() as { command_id: string }).command_id);
        await route.abort('failed');
    });
    await page.getByRole('button', { name: 'Simpan Format Nomor' }).click();
    await expect(page.getByText('Belum dapat dipastikan apakah tindakan ini tersimpan. Tekan Coba Lagi — sistem tidak akan mencatat dua kali.')).toBeVisible();
    await page.unroute(saving);
    const retried = page.waitForRequest((request) => saving(new URL(request.url())));
    await page.getByRole('button', { name: 'Coba Lagi' }).click();
    expect(((await retried).postDataJSON() as { command_id: string }).command_id).toBe(sent[0]);
    await expect(page.getByText('Format nomor Proyek disimpan.')).toBeVisible();

    // Sent input is not unsent: the type changes at once. Unsent input left through "Tinggalkan Halaman" goes.
    await page.getByLabel('Jenis nomor').selectOption('doc-01');
    await expect(page).toHaveURL(/jenis=doc-01/);
    await page.getByRole('button', { name: 'Tambah Format Nomor' }).click();
    await page.getByLabel('Templat nomor').fill('UJI/PNW/{tahun}/{nomor:4}');
    await page.getByLabel('Jenis nomor').selectOption('project');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Tinggalkan Halaman' }).click();
    await expect(page).toHaveURL(/jenis=project/);
    await expect(page.getByText('UJI/PRJ/{tahun}/{nomor:4}')).toBeVisible();
});
