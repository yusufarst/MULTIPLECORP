import { expect as base, type Page, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { dispatchRun } from '../dispatch-fixtures';
import { uniquePhoto } from '../evidence-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-041; J-INV-03; CJ-01; CJ-06; CJ-07; CJ-09; INT-186–INT-189; UXS-15; UXS-22): the worklist, the lots with their marker, a dispatch confirmed and answered in place, the likely duplicate, the routed refusal, its reversal and the Surat Jalan's draft, on the phone and the desktop.

test.describe.configure({ timeout: 420_000 });

const takeOwnLot = async (page: Page, lot: string, quantity: string) => {
    await page.locator('[data-slot="dispatch-line"]:visible').getByLabel('Jumlah keluar').fill(quantity);
    await page.locator('[data-slot="dispatch-lot"]').filter({ hasText: lot }).getByRole('button').first().click();
};
const record = async (page: Page) => {
    await page.locator('[data-slot="dispatch-line"]:visible').getByRole('button', { name: 'Catat Barang Keluar' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Barang Keluar' }).click();
};

for (const [name, width, height] of [['phone', 390, 844], ['desktop', 1280, 900]] as const) {
    test(`J-INV-03 on the ${name}: from the worklist, the lots with their marker, recorded and answered in place, reversed, the Surat Jalan's draft on PG-036 (CJ-01; CJ-06; criteria 1, 10, 14)`, async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        const run = await dispatchRun(page, `keluar-${name}-${String(Date.now())}`);
        await page.setViewportSize({ width, height });

        await page.goto(`/gudang/barang-keluar?perusahaan=${run.company}`);
        await expect(page.getByRole('heading', { name: 'Barang Keluar', exact: true })).toBeVisible();
        await expect(page.locator('#konten')).toContainText(run.name);

        await page.goto(`/gudang/barang-keluar/${run.project}`);
        await expect(page.getByRole('heading', { name: 'Keluarkan Barang', exact: true })).toBeVisible();
        await expect(page.locator('[data-slot="dispatch-lot"]').filter({ hasText: run.lot })).toContainText('Ambil Lebih Dulu');
        await expect(page.locator('[data-slot="dispatch-lot"]').filter({ hasText: 'perusahaan lain' })).toHaveCount(1);
        await expect(page.locator('#konten')).not.toContainText(run.other_name);

        await takeOwnLot(page, run.lot, '2');
        await record(page);
        await expect(page.locator('[data-slot="dispatch-line"]:visible')).toContainText('Barang keluar tercatat');
        await expect(page.locator('[data-slot="dispatch-record"]')).toHaveCount(1);

        // A second like it on the same day is a likely duplicate, committed only with the actor's reason (UXS-15).
        await takeOwnLot(page, run.lot, '2');
        await record(page);
        const warning = page.locator('[data-slot="duplicate-warning"]');
        await expect(warning).toBeVisible();
        await warning.getByRole('checkbox').first().check();
        await warning.getByRole('textbox').fill('Kiriman kedua hari ini');
        await record(page);
        await expect(page.locator('[data-slot="dispatch-record"]')).toHaveCount(2);

        await page.locator('[data-slot="dispatch-record"]').first().getByRole('button', { name: 'Batalkan Barang Keluar (Belum Berangkat)' }).click();
        const dialog = page.getByRole('alertdialog');
        await dialog.getByLabel(/Jumlah kembali/).first().fill('2');
        await dialog.getByLabel('Alasan').fill('Tidak jadi berangkat');
        await dialog.getByRole('button', { name: 'Batalkan Barang Keluar (Belum Berangkat)' }).click();
        await expect(page.getByRole('alertdialog')).toHaveCount(0);
        await expect(page.locator('[data-slot="dispatch-records"]')).toContainText('Pengembalian ke stok tercatat');
        await expect(page.locator('[data-slot="dispatch-record"]').first().getByRole('button', { name: 'Terbitkan Surat Jalan' })).toHaveCount(0);

        // A shipment refused comes back in part, with its reason; what is still out keeps its Surat Jalan (CM-06).
        await page.locator('[data-slot="dispatch-record"]').last().getByRole('button', { name: 'Catat Barang Kembali Tanpa Terkirim' }).click();
        const returned = page.getByRole('alertdialog');
        await returned.getByLabel(/Jumlah kembali/).first().fill('1');
        await returned.getByLabel('Alasan').fill('Ditolak di tempat');
        await returned.getByRole('button', { name: 'Catat Barang Kembali Tanpa Terkirim' }).click();
        await expect(page.getByRole('alertdialog')).toHaveCount(0);

        await page.locator('[data-slot="dispatch-record"]').last().getByRole('button', { name: 'Terbitkan Surat Jalan' }).click();
        await page.waitForURL('**/dokumen/**');
    });
}

test('refuses and routes another company\'s lot without the authority, with MSG-14, nothing recorded (CJ-09; UXS-22; XL-1; criterion 8)', async ({ page, browser }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await dispatchRun(page, `keluar-rute-${String(Date.now())}`);
    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
    const admin = await context.newPage();
    await signedIn(admin, 'keluar-admin', { plain: true });
    expect((await admin.request.post('/__fixtures/dispatch/plain', { data: { company: run.company } })).ok()).toBe(true);

    await admin.goto(`/gudang/barang-keluar/${run.project}`);
    await admin.locator('[data-slot="dispatch-line"]:visible').getByLabel('Jumlah keluar').fill('1');
    await admin.locator('[data-slot="dispatch-lot"]').filter({ hasText: 'perusahaan lain' }).getByRole('button').first().click();
    await admin.locator('[data-slot="dispatch-line"]:visible').getByLabel('Alasan alokasi antar-perusahaan').fill('Stok sendiri habis');
    await record(admin);
    await expect(admin.locator('[data-slot="dispatch-line"]:visible')).toContainText('diteruskan');
    await expect(admin.locator('[data-slot="dispatch-record"]')).toHaveCount(0);
    await context.close();
});

test('lists an ambiguous code\'s candidates for the user\'s choice and finds a line with "Cari Produk" (CJ-07; UXS-34; IP-15; MSG-37; TASK-088)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await dispatchRun(page, `keluar-kembar-${String(Date.now())}`);
    const made = await page.request.post('/__fixtures/dispatch/kembar', { data: { product: run.product } });
    expect(made.ok(), await made.text()).toBe(true);
    const twin = (await made.json()) as { code: string; name: string };

    await page.goto(`/gudang/barang-keluar/${run.project}`);
    const field = page.getByRole('textbox', { name: 'Pindai atau ketik kode' });
    await field.fill(twin.code);
    await field.press('Enter');
    await expect(page.getByRole('alert').filter({ hasText: `Kode ${twin.code} cocok dengan lebih dari satu barang. Pilih yang dimaksud.` }).locator('visible=true')).toBeVisible();

    // The two candidates, neither chosen for the user; the other product is not on the project and is refused as a scan of it would be.
    const candidates = page.locator('[data-slot="scan-candidates"]').getByRole('button');
    await expect(candidates).toHaveCount(2);
    await candidates.filter({ hasText: twin.name }).click();
    await expect(page.getByRole('alert').filter({ hasText: twin.name }).locator('visible=true')).toBeVisible();
    await expect(page.locator('[data-slot="scan-candidates"]')).toHaveCount(0);

    // The project's own product among them opens its line.
    await field.fill(twin.code);
    await field.press('Enter');
    await expect(candidates).toHaveCount(2);
    await candidates.filter({ hasNotText: twin.name }).click();
    await expect(page.getByRole('status').filter({ hasText: 'dibuka' }).locator('visible=true')).toBeVisible();
    await expect(page.locator('[data-slot="scan-candidates"]')).toHaveCount(0);

    // "Cari Produk" beside the field filters the project's lines and closes again.
    await page.getByRole('button', { name: 'Cari Produk' }).click();
    await page.getByRole('textbox', { name: 'Cari Produk' }).fill('tidak-ada-barang');
    await expect(page.locator('[data-slot="dispatch-lines"] li')).toHaveCount(0);
    await page.getByRole('button', { name: 'Cari Produk' }).click();
    await expect(page.getByRole('textbox', { name: 'Cari Produk' })).toHaveCount(0);
    await expect(page.locator('[data-slot="dispatch-lines"] li')).toHaveCount(1);
});

test('scans its serials, one announced twice and a code unknown, then corrects the unit shipped with its proof (CJ-07; UXS-34; IP-15; CM-36; criteria 5, 13)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await dispatchRun(page, `keluar-pindai-${String(Date.now())}`);
    const made = await page.request.post('/__fixtures/dispatch/serials', { data: { product: run.product, lot: run.lot, rack: run.rack } });
    expect(made.ok(), await made.text()).toBe(true);
    const { serials } = (await made.json()) as { serials: [string, string, string] };

    await page.goto(`/gudang/barang-keluar/${run.project}`);
    const scan = async (code: string) => {
        const field = page.getByRole('textbox', { name: 'Pindai atau ketik kode' });
        await field.fill(code);
        await field.press('Enter');
    };
    await expect(page.locator('[data-slot="dispatch-serials"]')).toContainText(serials[0]);

    const unknown = `TIDAK-${String(Date.now())}`;
    await scan(unknown);
    await expect(page.getByRole('alert').filter({ hasText: `Kode ${unknown} tidak dikenal.` }).locator('visible=true')).toBeVisible();

    await scan(serials[0]);
    await expect(page.getByRole('status').filter({ hasText: `Nomor seri ${serials[0]} ditambahkan.` }).locator('visible=true')).toBeVisible();
    await expect(page.locator('[data-slot="dispatch-line"]:visible').getByLabel('Jumlah keluar')).toHaveValue('1');
    await scan(serials[0]);
    await expect(page.getByRole('alert').filter({ hasText: `Nomor seri ${serials[0]} sudah dipindai.` }).locator('visible=true')).toBeVisible();

    await record(page);
    await expect(page.locator('[data-slot="dispatch-record"]')).toHaveCount(1);

    await page.locator('[data-slot="dispatch-record"]').getByRole('button', { name: 'Koreksi Nomor Seri atau Lot' }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByLabel('Nomor seri yang tercatat').selectOption({ label: serials[0] });
    await dialog.getByLabel('Nomor seri yang sebenarnya terkirim').selectOption({ index: 1 });
    await dialog.getByRole('button', { name: 'Tambah Bukti' }).click();

    const sheet = page.getByRole('dialog', { name: 'Unggah Dokumen' });
    await sheet.getByLabel('Unggah', { exact: true }).setInputFiles({ name: 'bukti-kirim.png', mimeType: 'image/png', buffer: uniquePhoto() });
    await expect(sheet.getByText('bukti-kirim.png')).toBeVisible();
    await sheet.getByLabel('Jenis bukti').selectOption({ label: 'Bukti kirim' });
    await sheet.getByLabel(/^Penerbit\b/).fill('Gudang Uji');
    await sheet.getByLabel('Tanggal dokumen').fill('2026-10-01');
    await sheet.getByRole('button', { name: 'Daftarkan Bukti' }).click();

    // The dialog comes back with what was entered.
    await expect(dialog.getByLabel('Nomor seri yang tercatat')).toHaveValue(/.+/);
    await dialog.getByLabel('Alasan').fill('Nomor keliru dicatat');
    await dialog.getByRole('button', { name: 'Koreksi Nomor Seri atau Lot' }).click();
    await expect(page.getByRole('alertdialog')).toHaveCount(0);
    await expect(page.locator('[data-slot="dispatch-records"]')).toContainText('Koreksi nomor seri atau lot tercatat');
});
