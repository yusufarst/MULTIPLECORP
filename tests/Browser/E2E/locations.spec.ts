import { expect, test } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from '../numbering-fixtures';

// TL-09 (TASK-028; J-MD-05; CJ-19; criteria 2–4): racks and minimum stock against the real routes — a rack created, a
// duplicate code refused at its field, the rack renamed and deactivated; a product's minimum set in base units from the
// lookup and changed from its row.

function stamp(): string {
    return `${String(Date.now()).slice(-6)}${String(Math.floor(Math.random() * 100))}`;
}

test('maintains racks and a product\'s minimum stock (J-MD-05; CJ-19)', async ({ page }) => {
    await ownerSignedIn(page, `lokasi-${stamp()}`);
    await numberingCompany(page, null);
    const code = `R${stamp()}`;
    const content = page.locator('#konten');

    await page.goto('/data-master/lokasi-rak');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Lokasi Rak');
    await page.getByRole('button', { name: 'Tambah Rak' }).first().click();
    let sheet = page.getByRole('dialog', { name: 'Tambah Rak' });
    await sheet.getByLabel(/^Kode rak/).fill(code);
    await sheet.getByLabel(/^Nama/).fill('Rak depan');
    await sheet.getByRole('button', { name: 'Tambah Rak' }).click();
    await expect(sheet).toBeHidden();
    await expect(content.getByText('Rak ditambahkan.')).toBeVisible();
    const row = page.getByRole('list', { name: 'Rak' }).getByRole('listitem').filter({ hasText: code });
    await expect(row).toContainText('Rak depan');

    await page.getByRole('button', { name: 'Tambah Rak' }).first().click();
    sheet = page.getByRole('dialog', { name: 'Tambah Rak' });
    await sheet.getByLabel(/^Kode rak/).fill(code.toLowerCase());
    await sheet.getByLabel(/^Nama/).fill('Rak kembar');
    await sheet.getByRole('button', { name: 'Tambah Rak' }).click();
    await expect(sheet.getByText('Kode ini sudah dipakai rak lain.')).toBeVisible();
    await sheet.getByRole('button', { name: 'Batal' }).click();
    await page.getByRole('alertdialog', { name: 'Isian yang belum dikirim' }).getByRole('button', { name: 'Tinggalkan Halaman' }).click();

    await row.getByRole('button', { name: 'Ubah Rak' }).click();
    sheet = page.getByRole('dialog', { name: `Ubah Rak ${code}` });
    await sheet.getByLabel(/^Nama/).fill('Rak depan atas');
    await sheet.getByRole('checkbox', { name: /^Aktif/ }).uncheck();
    await sheet.getByRole('button', { name: 'Simpan' }).click();
    await expect(sheet).toBeHidden();
    await expect(row).toContainText('Rak depan atas');
    await expect(row).toContainText('Nonaktif');

    await page.goto('/data-master/produk');
    const product = `Produk Minimum ${stamp()}`;
    await page.getByRole('button', { name: 'Tambah Produk' }).first().click();
    sheet = page.getByRole('dialog', { name: 'Tambah Produk' });
    await sheet.getByRole('textbox').first().fill(product);
    await sheet.getByLabel(/^Kode satuan/).fill('PCS');
    await sheet.getByRole('button', { name: 'Tambah Produk' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(product);

    await page.goto('/data-master/lokasi-rak');
    await page.getByRole('button', { name: 'Atur Stok Minimum' }).last().click();
    await page.getByRole('combobox', { name: 'Produk' }).fill(product);
    await page.getByRole('option', { name: product }).click();
    sheet = page.getByRole('dialog', { name: `Atur Stok Minimum — ${product}` });
    await sheet.getByLabel(/^Stok minimum/).fill('10');
    await sheet.getByRole('button', { name: 'Atur Stok Minimum' }).click();
    await expect(sheet).toBeHidden();
    const minimum = page.getByRole('list', { name: 'Stok Minimum' }).getByRole('listitem').filter({ hasText: product });
    await expect(minimum).toContainText('10');

    await minimum.getByRole('button', { name: 'Atur Stok Minimum' }).click();
    sheet = page.getByRole('dialog', { name: `Atur Stok Minimum — ${product}` });
    await sheet.getByLabel(/^Stok minimum/).fill('25');
    await sheet.getByRole('button', { name: 'Atur Stok Minimum' }).click();
    await expect(sheet).toBeHidden();
    await expect(minimum).toContainText('25');
});
