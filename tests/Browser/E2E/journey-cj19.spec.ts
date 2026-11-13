import { randomBytes } from 'node:crypto';

import { expect as base, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { armGoLive, capitalized, companyAdmin, letters, merged, secondContext, wibYear } from '../journey-k3-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-064; CJ-19; AC-01–AC-03; J-MD-01–J-MD-05; UXS-28; UXS-30; C-10; NM-03; criteria 7, 12): on the desktop, the Owner sets up the
// masters through the real pages — a company created on PG-060 with its legal data and its readiness list (J-MD-01), a client organization with
// its unit and PIC (J-MD-02), a supplier (J-MD-03), a product with its base unit, an alternate unit, a manufacturer barcode and an internal one,
// its serial policy and a purchase default (J-MD-04), a rack and the product's minimum stock (J-MD-05) — and the company's PROJECT numbering
// scheme on PG-061. UXS-28: the company created after go-live, its Admin's project on PG-013 refused with MSG-17 and the input kept; the Owner
// sees the numbering not started and runs "Mulai Penomoran" through its confirmation; the Admin's repeated submission draws the first number.
// UXS-30: a scheme effective in the past refused with the earliest date, the backward extension over uncovered dates accepted. The console and
// network clean in both contexts and ReconcileGuards clean for the company made here (TS-11).

// One continuous journey over PG-060, PG-054, PG-055, PG-056, PG-057, PG-061, PG-013 and PG-011 in two contexts.
test.describe.configure({ timeout: 300_000 });

const DESK = { width: 1280, height: 900 };
const UUID = /\/proyek\/[0-9a-f-]{36}$/;
const NAME = /^Nama\W*\(wajib\)/;

test('CJ-19: the masters set up by the Owner, a numbering scheme, an Admin\'s project refused with MSG-17 until the Owner\'s start, and a past scheme refused then extended backwards (J-MD-01–J-MD-05; UXS-28; UXS-30; criterion 7)', async ({ browser, page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    await signedIn(page, 'cj19-owner', { owner: true });
    const run = letters(8);
    const tag = run.toUpperCase();
    const year = wibYear();
    // Go-live at the year's start: the company below is created after it (UXS-28), its store being TASK-077's (NumberingFixture).
    const goLive = `${String(year)}-01-01`;
    await armGoLive(page, goLive);
    const content = page.locator('#konten');

    // J-MD-01 on PG-060: the company created, its legal form saved, its readiness list derived from what exists (ADMIN_FLOW §12.4).
    const code = `K${randomBytes(4).toString('hex').toUpperCase()}`;
    const legal = `CV Uji Master ${code}`;
    await page.goto('/pengaturan/perusahaan');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Perusahaan');
    await page.getByRole('button', { name: 'Tambah Perusahaan' }).first().click();
    const create = page.getByRole('dialog', { name: 'Tambah Perusahaan' });
    await create.getByLabel(/^Kode/).fill(code);
    await create.getByLabel(/^Nama badan usaha/).fill(legal);
    await create.getByRole('button', { name: 'Tambah Perusahaan' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(legal);
    await page.waitForURL(/perusahaan=[0-9a-f-]{36}/);
    const company = new URL(page.url()).searchParams.get('perusahaan') ?? '';
    await page.getByRole('tabpanel').getByLabel(/^Bentuk badan usaha/).fill('CV');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText('Data perusahaan disimpan.')).toBeVisible();
    const readiness = page.getByRole('list', { name: 'Kesiapan Perusahaan' });
    // UXS-28: the Owner sees that the company's projects cannot be numbered yet, and where to start it.
    const projectNumbering = readiness.getByRole('listitem').filter({ has: page.getByText('Belum dimulai — Proyek belum dapat dinomori untuk perusahaan ini.', { exact: true }) });
    await expect(projectNumbering.locator('[data-status="readiness_item.NOT_COMPLETE"]')).toBeVisible();
    await expect(projectNumbering.getByRole('link', { name: 'Buka Penomoran' })).toHaveAttribute('href', `/pengaturan/penomoran?perusahaan=${company}`);
    await expect(readiness.getByRole('listitem').filter({ hasText: 'Akses Admin' }).locator('[data-status="readiness_item.NOT_COMPLETE"]')).toBeVisible();

    // J-MD-02 on PG-054: the client organization, its unit with its address, the unit's PIC.
    const client = `SMA Master ${capitalized(run)}`;
    const unit = 'Tata Usaha';
    const pic = `Bu ${capitalized(run)}`;
    await page.goto('/data-master/klien');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Klien');
    await page.getByRole('button', { name: 'Buat Klien' }).first().click();
    let sheet = page.getByRole('dialog', { name: 'Buat Klien' });
    await sheet.getByLabel(NAME).fill(client);
    await sheet.getByRole('button', { name: 'Buat Klien' }).click();
    await expect(sheet).toBeHidden();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(client);
    await page.getByRole('button', { name: 'Tambah Unit' }).click();
    sheet = page.getByRole('dialog', { name: 'Tambah Unit' });
    await sheet.getByLabel(/^Nama unit/).fill(unit);
    await sheet.getByLabel(/^Alamat/).fill('Jl. Master No. 19');
    await sheet.getByRole('button', { name: 'Tambah Unit' }).click();
    await expect(page.getByText('Unit ditambahkan.')).toBeVisible();
    await expect(page.getByRole('heading', { name: unit })).toBeVisible();
    await page.getByRole('button', { name: 'Tambah PIC' }).click();
    sheet = page.getByRole('dialog', { name: 'Tambah PIC' });
    await sheet.getByLabel(/^Nama PIC/).fill(pic);
    await sheet.getByRole('button', { name: 'Tambah PIC' }).click();
    await expect(sheet).toBeHidden();
    await expect(page.getByText('PIC ditambahkan.')).toBeVisible();
    await expect(content).toContainText(pic);

    // J-MD-03 on PG-055: a supplier.
    const supplier = `CV Pemasok Master ${capitalized(run)}`;
    await page.goto('/data-master/pemasok');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Pemasok');
    await page.getByRole('button', { name: 'Buat Pemasok' }).first().click();
    sheet = page.getByRole('dialog', { name: 'Buat Pemasok' });
    await sheet.getByLabel(NAME).fill(supplier);
    await sheet.getByLabel(/^Kota/).fill('Sleman');
    await sheet.getByRole('button', { name: 'Buat Pemasok' }).click();
    await expect(sheet).toBeHidden();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(supplier);
    await expect(content).toContainText('Sleman');

    // J-MD-04 on PG-056: the product with its SKU and base unit, a box of twelve, a manufacturer barcode and an internal one, tracked by
    // serial number while no stock is on hand, and its purchase default changed forward with its history.
    const product = `Proyektor Master ${capitalized(run)}`;
    const manufacturer = `8997${String(Date.now()).slice(-9)}`;
    await page.goto('/data-master/produk');
    await page.getByRole('button', { name: 'Tambah Produk' }).first().click();
    sheet = page.getByRole('dialog', { name: 'Tambah Produk' });
    await sheet.getByLabel(NAME).fill(product);
    await sheet.getByLabel(/^SKU/).fill(`PRY-${tag}`);
    await sheet.getByLabel(/^Kode satuan/).fill('PCS');
    await sheet.getByLabel(/^Nama satuan/).fill('Pieces');
    await sheet.getByRole('button', { name: 'Tambah Produk' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(product);
    await expect(page.getByText(/^Satuan dasar: PCS · /)).toBeVisible();
    await page.getByRole('button', { name: 'Tambah Satuan' }).click();
    sheet = page.getByRole('dialog', { name: 'Tambah Satuan' });
    await sheet.getByLabel(/^Kode satuan/).fill('BOX');
    await sheet.getByLabel(/^Isi dalam satuan dasar/).fill('12');
    await sheet.getByRole('button', { name: 'Tambah Satuan' }).click();
    await expect(content.getByText('1 BOX = 12 PCS')).toBeVisible();
    await page.getByRole('button', { name: 'Daftarkan Barcode' }).click();
    sheet = page.getByRole('dialog', { name: 'Daftarkan Barcode' });
    await sheet.getByLabel(/^Kode barcode/).fill(manufacturer);
    await sheet.getByRole('button', { name: 'Daftarkan Barcode' }).click();
    await expect(sheet).toBeHidden();
    await expect(content.getByText('Barcode didaftarkan.')).toBeVisible();
    await expect(content.getByText(manufacturer)).toBeVisible();
    await page.getByRole('button', { name: 'Buat Barcode Internal' }).click();
    await expect(content.getByText(/^MC\d{8}$/)).toBeVisible();
    await page.getByRole('button', { name: 'Ubah Kebijakan' }).click();
    sheet = page.getByRole('dialog', { name: 'Ubah Kebijakan' });
    await sheet.getByRole('checkbox', { name: 'Dilacak per nomor seri' }).check();
    await sheet.getByRole('button', { name: 'Simpan' }).click();
    await expect(content.getByText('Kebijakan diperbarui.')).toBeVisible();
    await expect(content.getByText(/^Dilacak per nomor seri/)).toBeVisible();
    await page.getByRole('button', { name: 'Ubah Harga' }).first().click();
    sheet = page.getByRole('dialog', { name: 'Harga beli default' });
    await sheet.getByLabel(/^Harga \(Rp\)/).fill('4500000');
    await sheet.getByRole('button', { name: 'Ubah Harga' }).click();
    await expect(content.getByText('Harga default diperbarui.')).toBeVisible();
    await expect(page.locator('[data-slot="price-default"]').first().getByRole('list', { name: 'Riwayat harga' })).toContainText('4.500.000');

    // J-MD-05 on PG-057: a rack, and the product's minimum stock in base units.
    const rack = `R${tag.slice(0, 6)}`;
    await page.goto('/data-master/lokasi-rak');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Lokasi Rak');
    await page.getByRole('button', { name: 'Tambah Rak' }).first().click();
    sheet = page.getByRole('dialog', { name: 'Tambah Rak' });
    await sheet.getByLabel(/^Kode rak/).fill(rack);
    await sheet.getByLabel(/^Nama/).fill('Rak master');
    await sheet.getByRole('button', { name: 'Tambah Rak' }).click();
    await expect(sheet).toBeHidden();
    await expect(content.getByText('Rak ditambahkan.')).toBeVisible();
    // The list is ordered by code and paged with "Muat Lagi": on the shared CI database other runs' racks come first, so the
    // new rack is reached as an Admin reaches it — page by page.
    const racks = page.getByRole('list', { name: 'Rak' }).getByRole('listitem');
    const added = racks.filter({ hasText: rack });
    for (let turn = 0; turn < 20 && (await added.count()) === 0; turn += 1) {
        const more = content.getByRole('button', { name: 'Muat Lagi' });

        if ((await more.count()) === 0) break;

        const shown = await racks.count();
        await more.click();
        await expect.poll(() => racks.count()).toBeGreaterThan(shown);
    }
    await expect(added).toContainText('Rak master');
    await page.getByRole('button', { name: 'Atur Stok Minimum' }).last().click();
    await page.getByRole('combobox', { name: 'Produk' }).fill(product);
    await page.getByRole('option', { name: product }).click();
    sheet = page.getByRole('dialog', { name: `Atur Stok Minimum — ${product}` });
    await sheet.getByLabel(/^Stok minimum/).fill('4');
    await sheet.getByRole('button', { name: 'Atur Stok Minimum' }).click();
    await expect(sheet).toBeHidden();
    await expect(page.getByRole('list', { name: 'Stok Minimum' }).getByRole('listitem').filter({ hasText: product })).toContainText('4');

    // PG-061: the company's PROJECT scheme, from the middle of last year; no counter and no start yet.
    const numbering = `/pengaturan/penomoran?perusahaan=${company}&jenis=project`;
    const firstScheme = `${String(year - 1)}-07-01`;
    await page.goto(numbering);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Penomoran Dokumen');
    await page.getByRole('button', { name: 'Tambah Format Nomor' }).click();
    await page.getByLabel('Templat nomor').fill('K3/PRJ/{tahun}/{nomor:4}');
    await page.getByLabel(/^Berlaku mulai/).fill(firstScheme);
    await page.getByRole('button', { name: 'Simpan Format Nomor' }).click();
    await expect(page.getByText('Format nomor Proyek disimpan.')).toBeVisible();
    await expect(page.getByText('Belum ada penghitung.')).toBeVisible();

    // UXS-28: the company's Admin — `projects.view` and `projects.manage` there, nothing of numbering — in a context of its own with the
    // same go-live, signing in with TOTP (AU-18).
    const context = await secondContext(browser, testInfo, goLive);
    const admin = await context.newPage();
    const capturedAdmin = watch(admin);
    await signedIn(admin, 'cj19-admin');
    await companyAdmin(admin, company);
    const title = `Pengadaan proyektor ${capitalized(run)}`;
    await admin.goto('/proyek/baru');
    await expect(admin.getByRole('combobox', { name: /^Perusahaan/ })).toHaveValue(company);
    await admin.getByRole('combobox', { name: 'Klien' }).fill(client);
    await admin.getByRole('option', { name: client }).click();
    await admin.getByLabel(/^Unit/).selectOption({ label: unit });
    await admin.getByLabel(/^PIC/).selectOption({ label: pic });
    await admin.getByLabel(/^Judul proyek/).fill(title);
    await admin.getByRole('button', { name: 'Buat Proyek' }).click();

    // C-10 for the Admin: MSG-17 naming the type, the period and the company; no path to the numbering; the input kept on PG-013.
    await expect(admin.getByText(`Nomor Proyek untuk periode ${String(year)} di ${legal} belum dimulai. Hanya Owner yang dapat memulai penomoran atau menetapkan nomor awal. Hubungi Owner, lalu ulangi tindakan ini.`).first()).toBeVisible();
    await expect(admin.getByRole('link', { name: 'Mulai Penomoran' })).toHaveCount(0);
    await expect(admin.getByLabel(/^Judul proyek/)).toHaveValue(title);
    await expect(admin.getByLabel(/^PIC/)).toHaveValue(/.+/);
    expect(admin.url()).toContain('/proyek/baru');

    // The Owner's start on PG-061 through its confirmation and its one required tick (HO-39).
    await page.reload();
    await page.getByLabel(`Nomor pertama periode ${String(year)}`).fill('7');
    await page.getByRole('button', { name: 'Mulai Penomoran' }).click();
    const starting = page.getByRole('alertdialog');
    await expect(starting.getByText(`Periode ${String(year)} → nomor pertama 7`)).toBeVisible();
    await expect(starting.getByText('Mulai hari ini, Proyek tidak lagi dinomori di luar sistem ini.')).toBeVisible();
    await expect(starting.getByRole('button', { name: 'Mulai Penomoran' })).toBeDisabled();
    await starting.getByRole('checkbox').check();
    await starting.getByRole('button', { name: 'Mulai Penomoran' }).click();
    await expect(page.getByText('Penomoran Proyek dimulai.')).toBeVisible();
    await expect(page.getByRole('cell', { name: '7', exact: true })).toBeVisible();

    // The Admin's same submission again: the project created with the period's first number.
    await admin.getByRole('button', { name: 'Buat Proyek' }).click();
    await admin.waitForURL(UUID);
    await expect(admin.getByText('Proyek dibuat dan nomornya ditetapkan.')).toBeVisible();
    await expect(admin.getByRole('heading', { level: 1 })).toHaveText(title);
    await expect(admin.locator('#konten').getByText(`K3/PRJ/${String(year)}/0007`).first()).toBeVisible();
    await expect(admin.locator('#konten')).toContainText(client);

    // UXS-30: a new scheme taking effect in the past. Without the extension the date field stops at today; asked as an extension of a
    // date the earlier scheme already covers, the answer names the earliest date; over the dates no scheme covered, it is accepted.
    await page.reload();
    await page.getByRole('button', { name: 'Tambah Format Nomor' }).click();
    const recording = (await page.getByText(/^Tanggal pencatatan: /).first().innerText()).replace('Tanggal pencatatan: ', '').trim();
    await page.getByLabel('Templat nomor').fill('K3/PROJ/{tahun}/{nomor:4}');
    const effective = page.getByLabel(/^Berlaku mulai/);
    await expect(effective).toHaveAttribute('min', /^\d{4}-\d{2}-\d{2}$/);
    await effective.fill(`${String(year - 1)}-12-01`);
    expect(await effective.evaluate((input) => (input as HTMLInputElement).validity.rangeUnderflow), 'a date before today is refused at its field').toBe(true);
    await page.getByRole('checkbox', { name: 'Perluas ke tanggal lebih awal yang belum memiliki format' }).check();
    await expect(effective).not.toHaveAttribute('min');
    await effective.fill(`${String(year - 1)}-12-01`);
    await page.getByRole('button', { name: 'Simpan Format Nomor' }).click();
    await expect(page.getByText(`Format nomor tidak dapat berlaku mundur. Tanggal paling awal: ${recording}.`)).toBeVisible();
    await expect(page.getByLabel('Templat nomor')).toHaveValue('K3/PROJ/{tahun}/{nomor:4}');
    await effective.fill(`${String(year - 1)}-06-01`);
    await page.getByRole('button', { name: 'Simpan Format Nomor' }).click();
    await expect(page.getByText('Format nomor Proyek disimpan.')).toBeVisible();
    await expect(page.getByRole('cell', { name: 'K3/PROJ/{tahun}/{nomor:4}' })).toBeVisible();

    // The company's readiness follows: its projects numbered, its Admin access granted.
    await page.goto(`/pengaturan/perusahaan?perusahaan=${company}`);
    await expect(readiness.getByRole('listitem').filter({ has: page.getByText('Penomoran Proyek', { exact: true }) }).locator('[data-status="readiness_item.COMPLETE"]')).toBeVisible();
    await expect(readiness.getByRole('listitem').filter({ hasText: 'Akses Admin' }).locator('[data-status="readiness_item.COMPLETE"]')).toBeVisible();

    await guardsClean(page, testInfo, [company]);
    await clean(merged(captured, capturedAdmin), testInfo);
    await context.close();
});
