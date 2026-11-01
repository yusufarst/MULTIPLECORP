import { expect as base, type Page, test } from '@playwright/test';

import { dispatchRun } from '../dispatch-fixtures';

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
        await expect(page.locator('[data-slot="dispatch-lot"]').filter({ hasText: run.other_code })).toContainText('perusahaan lain');
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
        await page.locator('[data-slot="dispatch-line"]:visible').getByRole('button', { name: 'Catat Barang Keluar' }).click();
        await expect(page.locator('[data-slot="dispatch-record"]')).toHaveCount(2);

        await page.locator('[data-slot="dispatch-record"]').first().getByRole('button', { name: 'Batalkan Barang Keluar (Belum Berangkat)' }).click();
        const dialog = page.getByRole('alertdialog');
        await dialog.getByLabel(/Jumlah kembali/).first().fill('2');
        await dialog.getByLabel('Alasan').fill('Tidak jadi berangkat');
        await dialog.getByRole('button', { name: 'Batalkan Barang Keluar (Belum Berangkat)' }).click();
        await expect(page.getByRole('alertdialog')).toHaveCount(0);

        await page.locator('[data-slot="dispatch-record"]').last().getByRole('button', { name: 'Terbitkan Surat Jalan' }).click();
        await page.waitForURL('**/dokumen/**');
    });
}

test('refuses and routes another company\'s lot without the authority, with MSG-14, nothing recorded (CJ-09; UXS-22; XL-1; criterion 8)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await dispatchRun(page, `keluar-rute-${String(Date.now())}`);
    await page.request.post('/__fixtures/dispatch/plain', { data: { company: run.company } });

    await page.goto(`/gudang/barang-keluar/${run.project}`);
    await page.locator('[data-slot="dispatch-line"]:visible').getByLabel('Jumlah keluar').fill('1');
    await page.locator('[data-slot="dispatch-lot"]').filter({ hasText: run.other_code }).getByRole('button').first().click();
    await page.locator('[data-slot="dispatch-line"]:visible').getByLabel('Alasan alokasi antar-perusahaan').fill('Stok sendiri habis');
    await record(page);
    await expect(page.locator('[data-slot="dispatch-line"]:visible')).toContainText('diteruskan');
    await expect(page.locator('[data-slot="dispatch-record"]')).toHaveCount(0);
});
