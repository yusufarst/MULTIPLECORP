import { expect, type Page, test } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-09 (TASK-026; J-MD-02; J-MD-03; CJ-19; criteria 1, 2, 4, 7, 8): the party masters against the real routes — a client
// created inline from the project form's stand-in on the phone and on the desktop, its unit, address and PIC chosen on
// return with the form's input kept; a supplier the same from the purchase form's —; then PG-054 search-first: a client
// with a unit and its address, a duplicate name answered with the list and created with a reason, a taken unit name
// refused at its field, an archive saying what continues; and PG-055: a supplier created, edited and archived.

// The required name's label reads "Nama", its marker and "(wajib)"; "Nama unit" and "Nama PIC" are other fields.
const NAME = /^Nama\W*\(wajib\)/;

function stamp(): string {
    return `${String(Date.now())}${String(Math.floor(Math.random() * 1000))}`;
}

async function signedInWithCompany(page: Page, run: string): Promise<void> {
    await ownerSignedIn(page, `${run}-${stamp()}`);
    await numberingCompany(page, null);
}

for (const width of [390, 1280]) {
    test(`creates a client inline from the project form and returns with it chosen at ${String(width)} px (J-MD-02; criterion 8)`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await signedInWithCompany(page, `pihak-inline-${String(width)}`);
        const name = `Sekolah Sebaris ${stamp()}`;
        await page.goto('/__fixtures/formulir-pihak');
        const form = page.getByTestId('project-form');
        await form.getByLabel('Rincian lain').fill('Catatan yang tetap');

        await form.getByRole('combobox', { name: 'Klien' }).fill(name);
        await page.getByRole('button', { name: 'Buat Klien' }).click();
        const sheet = page.getByRole('dialog', { name: 'Buat Klien' });
        await expect(sheet.getByLabel(NAME)).toHaveValue(name);
        await sheet.getByLabel(/^Nama unit/).fill('Tata Usaha');
        await sheet.getByLabel(/^Alamat/).fill('Jl. Sebaris No. 1');
        await sheet.getByLabel(/^Nama PIC/).fill('Bu Sebaris');
        await sheet.getByRole('button', { name: 'Buat Klien' }).click();

        const chosen = page.getByTestId('client-chosen');
        await expect(chosen.getByRole('listitem')).toHaveText([`Terpilih: ${name}`, 'Terpilih: Tata Usaha', 'Terpilih: Jl. Sebaris No. 1', 'Terpilih: Bu Sebaris']);
        await expect(form.getByLabel('Rincian lain')).toHaveValue('Catatan yang tetap');
        await expect(page).toHaveURL(/\/__fixtures\/formulir-pihak$/);
    });
}

test('creates a supplier inline from the purchase form and returns with it chosen (J-MD-03; criterion 8)', async ({ page }) => {
    await signedInWithCompany(page, 'pihak-pemasok-inline');
    const name = `Toko Sebaris ${stamp()}`;
    await page.goto('/__fixtures/formulir-pihak');
    const form = page.getByTestId('purchase-form');
    await form.getByLabel('Rincian lain').fill('Pesanan alat tulis');

    await form.getByRole('combobox', { name: 'Pemasok' }).fill(name);
    await page.getByRole('button', { name: 'Buat Pemasok' }).click();
    const sheet = page.getByRole('dialog', { name: 'Buat Pemasok' });
    await sheet.getByLabel(/^Kota/).fill('Sleman');
    await sheet.getByRole('button', { name: 'Buat Pemasok' }).click();

    await expect(page.getByTestId('supplier-chosen')).toHaveText(`Terpilih: ${name}`);
    await expect(form.getByLabel('Rincian lain')).toHaveValue('Pesanan alat tulis');
});

test('creates a client search-first with a unit, answers a duplicate name with the list, and archives saying what continues (J-MD-02; CJ-19)', async ({ page }) => {
    await signedInWithCompany(page, 'pihak-klien');
    const name = `Universitas Uji ${stamp()}`;
    await page.goto('/data-master/klien');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Klien');

    await page.getByRole('button', { name: 'Buat Klien' }).first().click();
    let sheet = page.getByRole('dialog', { name: 'Buat Klien' });
    await sheet.getByLabel(NAME).fill(name);
    await sheet.getByRole('button', { name: 'Buat Klien' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);

    await page.getByRole('button', { name: 'Tambah Unit' }).click();
    sheet = page.getByRole('dialog', { name: 'Tambah Unit' });
    await sheet.getByLabel(/^Nama unit/).fill('Fakultas Teknik');
    await sheet.getByLabel(/^Alamat/).fill('Jl. Kampus 1');
    await sheet.getByRole('button', { name: 'Tambah Unit' }).click();
    await expect(page.getByText('Unit ditambahkan.')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Fakultas Teknik' })).toBeVisible();
    await expect(page.getByText('Jl. Kampus 1')).toBeVisible();

    await page.getByRole('button', { name: 'Tambah Unit' }).click();
    sheet = page.getByRole('dialog', { name: 'Tambah Unit' });
    await sheet.getByLabel(/^Nama unit/).fill('fakultas  teknik');
    await sheet.getByRole('button', { name: 'Tambah Unit' }).click();
    await expect(sheet.getByText('Nama unit ini sudah ada di klien ini.')).toBeVisible();
    await sheet.getByRole('button', { name: 'Batal' }).click();
    await page.getByRole('alertdialog', { name: 'Isian yang belum dikirim' }).getByRole('button', { name: 'Tinggalkan Halaman' }).click();

    await page.getByRole('button', { name: 'Semua klien' }).click();
    await page.getByRole('button', { name: 'Buat Klien' }).first().click();
    sheet = page.getByRole('dialog', { name: 'Buat Klien' });
    await sheet.getByLabel(NAME).fill(name);
    await expect(sheet.getByRole('link', { name })).toBeVisible();
    await sheet.getByRole('button', { name: 'Buat Klien' }).click();
    const warning = sheet.locator('[data-slot="duplicate-warning"]');
    await expect(warning).toContainText(name);
    await warning.getByRole('checkbox').check();
    await warning.getByRole('textbox').fill('Kampus di kota lain');
    await sheet.getByRole('button', { name: 'Buat Klien' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);

    await page.getByRole('button', { name: 'Tindakan Lain' }).click();
    await page.getByRole('menuitem', { name: 'Arsipkan' }).click();
    const confirm = page.getByRole('alertdialog', { name: 'Arsipkan Klien' });
    await expect(confirm).toContainText('Proyek yang berjalan tetap berlanjut');
    await confirm.getByRole('textbox').fill('Tidak lagi dilayani');
    await confirm.getByRole('button', { name: 'Arsipkan' }).click();
    await expect(page.getByText('Diarsipkan', { exact: true }).first()).toBeVisible();
});

test('creates, edits and archives a supplier chosen directly (J-MD-03; CJ-19)', async ({ page }) => {
    await signedInWithCompany(page, 'pihak-pemasok');
    const name = `CV Pemasok ${stamp()}`;
    await page.goto('/data-master/pemasok');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pemasok');

    await page.getByRole('button', { name: 'Buat Pemasok' }).first().click();
    const sheet = page.getByRole('dialog', { name: 'Buat Pemasok' });
    await sheet.getByLabel(NAME).fill(name);
    await sheet.getByLabel(/^Kota/).fill('Bantul');
    await sheet.getByRole('button', { name: 'Buat Pemasok' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);
    await expect(page.getByText('Bantul')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Riwayat Harga Beli' })).toBeVisible();

    await page.getByRole('button', { name: 'Tindakan Lain' }).click();
    await page.getByRole('menuitem', { name: 'Ubah' }).click();
    const edit = page.getByRole('dialog', { name: 'Ubah Pemasok' });
    await edit.getByLabel(/^Kota/).fill('Kulon Progo');
    await edit.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByText('Perubahan tersimpan.')).toBeVisible();
    await expect(page.getByText('Kulon Progo')).toBeVisible();

    await page.getByRole('button', { name: 'Tindakan Lain' }).click();
    await page.getByRole('menuitem', { name: 'Arsipkan' }).click();
    const confirm = page.getByRole('alertdialog', { name: 'Arsipkan Pemasok' });
    await expect(confirm).toContainText('Pembelian, penerimaan barang, dan retur yang berjalan tetap berlanjut');
    await confirm.getByRole('textbox').fill('Tutup usaha');
    await confirm.getByRole('button', { name: 'Arsipkan' }).click();
    await expect(page.locator('#konten').getByText('Pemasok diarsipkan.')).toBeVisible();
});
