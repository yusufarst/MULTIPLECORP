import { expect as base, test } from '@playwright/test';

import { clean, guardsClean, watch } from '../journey-fixtures';
import { addPurchaseLine, chooseSupplier, purchaseRun } from '../purchase-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-062; CJ-02; J-PUR-01; J-PUR-02; AC-05; BR-PUR-03; NX-04; criteria 2, 7, 8): on the desktop, a confirmed
// project's shortage bought from PG-011's *Pengadaan* from two suppliers, each purchase's line linked to the project's one
// demand line — never a second demand —, the company shown and not chosen; the first left without a PO, the second with
// its PO issued on PG-036; both standing for receipt alike; then a replenishment from PG-015 with its company chosen; no
// cash and no stock from any purchase; ReconcileGuards clean for the run's company, the console and network clean.

// One continuous journey of three saves and an issue over PG-011, PG-017, PG-016, PG-036 and PG-015 after its fixtures.
test.describe.configure({ timeout: 300_000 });

const UUID = /\/pembelian\/[0-9a-f-]{36}$/;

test('CJ-02: a project bought from two suppliers on one demand, one purchase with its PO and one without, and a replenishment, none moving cash or stock (J-PUR-01; J-PUR-02; AC-05)', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await purchaseRun(page, `cj02-${String(Date.now())}`);
    const captured = watch(page);
    const content = page.locator('#konten');
    const year = String(new Date().getFullYear());

    /** J-PUR-01 from PG-011's *Pengadaan*: the shortage prefilled on its demand line, the supplier chosen, saved on PG-016. */
    const buyShortage = async (supplier: string, quantity: string): Promise<string> => {
        await page.locator('[data-slot="procurement"]').getByRole('link', { name: 'Buat Pembelian' }).click();
        await page.waitForURL(new RegExp(`/pembelian/baru\\?proyek=${run.project}$`));
        await expect(content).toContainText(run.project_number);
        await expect(content).toContainText(run.code);
        await expect(page.getByLabel(/^Perusahaan/)).toHaveCount(0);
        const row = page.locator('[data-slot="line-editor"] [data-line]').first();
        await expect(row.getByRole('textbox', { name: 'Jumlah' })).toHaveValue(/10/);
        await chooseSupplier(page, supplier);
        await row.getByRole('textbox', { name: 'Jumlah' }).fill(quantity);
        await row.getByRole('textbox', { name: 'Harga' }).fill('90000');
        await page.getByRole('button', { name: 'Simpan Pembelian' }).click();
        await page.waitForURL(UUID);

        const id = new URL(page.url()).pathname.split('/').pop() ?? '';
        await expect(page.getByText('Belum ada uang keluar dan belum ada barang masuk.')).toBeVisible();
        await expect(content).toContainText('UJI/BELI/');
        await expect(content).toContainText(supplier);
        await expect(content).toContainText('Belum ada penerimaan.');
        await expect(page.locator('[data-line="1"]')).toContainText('Untuk item proyek no. 1');
        // Equivalent purchases proceed alike: each stands for receipt, with or without its PO (AC-05; BR-XD-04).
        await expect(content.getByRole('link', { name: 'Terima Barang' })).toHaveAttribute('href', `/gudang/barang-masuk/${id}`);

        return id;
    };

    await page.goto(`/proyek/${run.project}`);
    await expect(page.locator('[data-slot="procurement"]')).toContainText('Belum ada pembelian untuk proyek ini.');

    // The first supplier's purchase stands without a PO.
    const withoutPo = await buyShortage(run.supplier, '4');
    await expect(content).toContainText('Belum ada dokumen. PO bersifat opsional.');
    await content.getByRole('link', { name: run.project_number, exact: true }).first().click();
    await page.waitForURL(new RegExp(`/proyek/${run.project}$`));

    // The second supplier's purchase of the same demand, its PO issued on PG-036 with its number (J-DOC-01; MSG-39).
    const withPo = await buyShortage(run.other_supplier, '6');
    await page.getByRole('button', { name: 'Terbitkan PO' }).click();
    await page.waitForURL('**/dokumen/**');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Purchase Order / PO');
    await expect(content).toContainText(run.other_supplier);
    await content.getByRole('button', { name: 'Terbitkan Purchase Order / PO', exact: true }).click();
    const confirm = page.getByRole('alertdialog', { name: 'Terbitkan Purchase Order / PO?' });
    await expect(confirm).toContainText('Nomor akan ditetapkan dan tidak dapat diubah.');
    await confirm.getByRole('button', { name: 'Terbitkan Purchase Order / PO' }).click();
    await expect(confirm).toBeHidden();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(new RegExp(`^Purchase Order / PO UJI/PO/${year}/\\d{4}$`));

    // The PO changes nothing of the purchase: still no receipt, still to be received (BR-XD-04).
    await page.goto(`/pembelian/${withPo}`);
    await expect(content.getByRole('link', { name: new RegExp(`^Purchase Order / PO · UJI/PO/${year}/\\d{4}$`) })).toBeVisible();
    await expect(content).toContainText('Belum ada penerimaan.');
    await expect(content.getByRole('link', { name: 'Terima Barang' })).toHaveAttribute('href', `/gudang/barang-masuk/${withPo}`);
    await content.getByRole('link', { name: run.project_number, exact: true }).first().click();
    await page.waitForURL(new RegExp(`/proyek/${run.project}$`));

    // PG-011: both suppliers against the project, each with its own remainder, and still one confirmed demand line.
    const procurement = page.locator('[data-slot="procurement"]');
    await expect(procurement.locator(`a[href="/pembelian/${withoutPo}"]`)).toContainText(run.supplier);
    await expect(procurement.locator(`a[href="/pembelian/${withPo}"]`)).toContainText(run.other_supplier);
    await expect(procurement).toContainText('Sisa 4');
    await expect(procurement).toContainText('Sisa 6');
    await expect(page.getByRole('list', { name: 'Baris terkonfirmasi' }).getByRole('listitem')).toHaveCount(1);

    // J-PUR-02 from PG-015: a replenishment, its company an explicit field (WF-PUR-02), no PO.
    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Pembelian' }).click();
    await page.waitForURL('**/pembelian');
    await expect(page.getByRole('heading', { name: 'Pembelian', exact: true })).toBeVisible();
    await content.getByRole('link', { name: 'Buat Pembelian' }).first().click();
    await page.waitForURL(/\/pembelian\/baru$/);
    const save = page.getByRole('button', { name: 'Simpan Pembelian' });
    await expect(save).toBeDisabled();
    await expect(page.getByText('Pilih pemasok dan isi jumlah serta harga setiap baris.')).toBeVisible();
    await content.getByLabel(/^Perusahaan/).selectOption(run.company);
    await expect(content.getByLabel(/^Perusahaan/)).toHaveValue(run.company);
    await chooseSupplier(page, run.supplier);
    await addPurchaseLine(page, 0, run.goods, '10', '100000');
    await expect(content).toContainText('Rp1.000.000');
    await expect(save).toBeEnabled();
    await save.click();
    await page.waitForURL(UUID);
    await expect(page.getByText('Belum ada uang keluar dan belum ada barang masuk.')).toBeVisible();
    await expect(content).toContainText('UJI/BELI/');
    await expect(content).toContainText('Pengisian stok');
    await expect(content).toContainText('Belum ada penerimaan.');
    await expect(content).toContainText('Belum ada dokumen. PO bersifat opsional.');

    // PG-015 filtered to the run's company: the three purchases, two of them the project's, each supplier's own.
    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Pembelian' }).click();
    await page.waitForURL('**/pembelian');
    await content.getByLabel(/^Perusahaan/).selectOption(run.company);
    await page.waitForURL(new RegExp(`perusahaan=${run.company}`));
    const rows = page.getByRole('table', { name: 'Pembelian' }).getByRole('row');
    await expect(rows).toHaveCount(4);
    await expect(rows.filter({ hasText: run.project_number })).toHaveCount(2);
    await expect(rows.filter({ hasText: run.supplier })).toHaveCount(2);
    await expect(rows.filter({ hasText: run.other_supplier })).toHaveCount(1);

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});
