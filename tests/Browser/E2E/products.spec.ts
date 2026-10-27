import { expect, type Page, test } from '@playwright/test';

import { uniquePhoto } from '../evidence-fixtures';
import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-09 (TASK-027; J-MD-04; CJ-19; criteria 1–6): the catalogue against the real routes — a product created with its
// SKU and base unit; an alternate unit as an exact multiple; an internal barcode from the sequence and its label printed
// by the browser, writing nothing; a manufacturer code of another product refused at its field; the serial policy
// switched while no stock is on hand; a purchase default changed forward with its history; an image attached.

const NAME = /^Nama\W*\(wajib\)/;

function stamp(): string {
    return `${String(Date.now())}${String(Math.floor(Math.random() * 1000))}`;
}

async function createProduct(page: Page, name: string, sku: string): Promise<void> {
    await page.goto('/data-master/produk');
    await page.getByRole('button', { name: 'Tambah Produk' }).first().click();
    const sheet = page.getByRole('dialog', { name: 'Tambah Produk' });
    await sheet.getByLabel(NAME).fill(name);
    await sheet.getByLabel(/^SKU/).fill(sku);
    await sheet.getByLabel(/^Kode satuan/).fill('PCS');
    await sheet.getByLabel(/^Nama satuan/).fill('Pieces');
    await sheet.getByRole('button', { name: 'Tambah Produk' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);
}

test('builds a product: units, barcodes and the label, serial policy, a price default and an image (J-MD-04; CJ-19)', async ({ page }) => {
    await ownerSignedIn(page, `produk-${stamp()}`);
    await numberingCompany(page, null);
    const id = stamp();
    const name = `Pulpen Uji ${id}`;
    await createProduct(page, name, `PLP-${id}`);
    await expect(page.getByText('Satuan dasar: PCS · Pieces')).toBeVisible();

    await page.getByRole('button', { name: 'Tambah Satuan' }).click();
    let sheet = page.getByRole('dialog', { name: 'Tambah Satuan' });
    await sheet.getByLabel(/^Kode satuan/).fill('BOX');
    await sheet.getByLabel(/^Isi dalam satuan dasar/).fill('12');
    await sheet.getByRole('button', { name: 'Tambah Satuan' }).click();
    await expect(page.locator('#konten').getByText('1 BOX = 12 PCS')).toBeVisible();

    await page.getByRole('button', { name: 'Buat Barcode Internal' }).click();
    const internal = page.locator('#konten').getByText(/^MC\d{8}$/);
    await expect(internal).toBeVisible();
    const code = (await internal.textContent()) ?? '';
    // The browser's print stands in for the dialog the test cannot answer; nothing is sent to the server.
    await page.evaluate((printed) => {
        window.print = () => { document.body.dataset.printed = printed; };
    }, code);
    await page.getByRole('button', { name: 'Cetak Label' }).click();
    await expect(page.locator('body')).toHaveAttribute('data-printed', code);
    await expect(page.locator('#label-print svg')).toHaveAttribute('aria-label', code);

    await createProduct(page, `Map Uji ${id}`, `MAP-${id}`);
    await page.getByRole('button', { name: 'Daftarkan Barcode' }).click();
    sheet = page.getByRole('dialog', { name: 'Daftarkan Barcode' });
    await sheet.getByLabel(/^Kode barcode/).fill(` ${code.toLowerCase()} `);
    await sheet.getByRole('button', { name: 'Daftarkan Barcode' }).click();
    await expect(sheet.getByText('Barcode ini sudah terdaftar pada produk lain.')).toBeVisible();
    await sheet.getByRole('button', { name: 'Batal' }).click();
    await page.getByRole('alertdialog', { name: 'Isian yang belum dikirim' }).getByRole('button', { name: 'Tinggalkan Halaman' }).click();

    await page.getByRole('button', { name: 'Ubah Kebijakan' }).click();
    sheet = page.getByRole('dialog', { name: 'Ubah Kebijakan' });
    await sheet.getByLabel('Dilacak per nomor seri').check();
    await sheet.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.locator('#konten').getByText('Kebijakan diperbarui.')).toBeVisible();
    await expect(page.locator('#konten').getByText(/^Dilacak per nomor seri/)).toBeVisible();

    await page.getByRole('button', { name: 'Ubah Harga' }).first().click();
    sheet = page.getByRole('dialog', { name: 'Harga beli default' });
    await sheet.getByLabel(/^Harga \(Rp\)/).fill('4500');
    await sheet.getByRole('button', { name: 'Ubah Harga' }).click();
    await expect(page.locator('#konten').getByText('Harga default diperbarui.')).toBeVisible();
    await expect(page.locator('[data-slot="price-default"]').first().getByRole('list', { name: 'Riwayat harga' })).toContainText('4.500');

    await page.getByLabel('Tambah Gambar', { exact: true }).setInputFiles({ name: 'produk.png', mimeType: 'image/png', buffer: uniquePhoto() });
    await expect(page.getByText('produk.png')).toBeVisible();
    await page.getByRole('button', { name: 'Pasang Gambar' }).click();
    await expect(page.locator('#konten').getByText('Gambar dipasang.')).toBeVisible();
    await expect(page.locator('#konten').getByText('Utama', { exact: true })).toBeVisible();
});
