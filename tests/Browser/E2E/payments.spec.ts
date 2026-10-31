import { expect as base, type Page, test } from '@playwright/test';

import { invoiceRun, type InvoiceRun } from '../invoice-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-051; J-FIN-03; S2-J06; CJ-04's partial, full and overpayment, credit and the duplicate; CJ-21's lost answer and retry; J-FIN-07's statement lines): a payment with its proof and one application on the phone; several applications and the statement line at the desk, the recorded payment first on PG-045; "Terapkan", "Pindahkan Penerapan"
// and the contra in place; a likely duplicate's warning and override; a line recorded, a flagged payment citing it and a mistyped one voided.

test.describe.configure({ timeout: 240_000 });

async function chooseClient(page: Page, run: InvoiceRun) {
    const lookup = page.getByRole('combobox', { name: /Klien yang membayar/ });
    await lookup.fill(run.client_name);
    await page.getByRole('option', { name: new RegExp(run.client_name) }).first().click();
}

async function record(page: Page) {
    await page.getByRole('button', { name: 'Catat Pembayaran' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Pembayaran' }).click();
}

test('records a payment with its proof and one application on the phone, PG-045 answering with it first (J-FIN-03; S2-J06; AC-10; INT-251)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const run = await invoiceRun(page, `bayar-hp-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true });

    await page.goto(`/keuangan/pembayaran/baru?invoice=${run.invoice ?? ''}`);
    await page.getByLabel(/^Jumlah/).fill('300000');
    await page.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    await page.getByRole('combobox', { name: /^Bukti/ }).selectOption({ index: 1 });
    await page.getByTestId('application').getByLabel(/^Diterapkan/).fill('300000');
    await expect(page.getByText('Rp700.000').first()).toBeHidden();
    await record(page);

    await expect(page).toHaveURL(/\/keuangan\/pembayaran\?.*tercatat=/);
    await expect(page.getByTestId('payment')).toContainText('Rp300.000');
    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page.locator('#konten')).toContainText('Rp700.000');
});

test('records several applications and the statement line at the desk, the rest as credit, the second payment clearing the receivable (J-FIN-03; CJ-04; annex 1, 3; BD-03)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `bayar-meja-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, line: '1200000' });

    await page.goto(`/keuangan/pembayaran/baru?perusahaan=${run.company}`);
    await chooseClient(page, run);
    await expect(page.getByTestId('application')).toHaveCount(1);
    await page.getByLabel(/^Jumlah/).fill('1200000');
    await page.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    await page.getByLabel(/^Baris mutasi bank/).selectOption({ index: 1 });
    await page.getByTestId('application').getByLabel(/^Diterapkan/).fill('1000000');
    await expect(page.locator('#konten')).toContainText('Rp200.000');
    await record(page);

    await expect(page).toHaveURL(/tercatat=/);
    await expect(page.getByTestId('payment')).toContainText('Rp1.200.000');
    await expect(page.getByTestId('payment')).toContainText('Rp200.000');
    await expect(page.getByTestId('payment-application')).toContainText('Rp1.000.000');
    await expect(page.getByTestId('payment')).toContainText(`TRX-${run.code}`);
});

test('refuses a payment without proof until its reason is given, and shows it Tanpa Bukti (L-29; UXS-16; criterion 9)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `bayar-tanpa-bukti-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true });

    await page.goto(`/keuangan/pembayaran/baru?invoice=${run.invoice ?? ''}`);
    await page.getByLabel(/^Jumlah/).fill('100000');
    await page.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    await record(page);
    await expect(page.locator('#konten')).toContainText('Tanpa bukti, alasannya wajib diisi.');
    await expect(page.getByLabel(/^Jumlah/)).toHaveValue(/100/);

    await page.getByLabel(/^Alasan tanpa bukti/).fill('Bukti menyusul dari bendahara');
    await record(page);
    await expect(page).toHaveURL(/tercatat=/);
    await expect(page.getByTestId('payment').locator('[data-status="payment.WITHOUT_PROOF"]')).toBeVisible();
});

test('warns on a likely duplicate, the override naming the match with its reason (CJ-04; IP-05; UXS-16; INT-015; criterion 4)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `bayar-ganda-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '500000' });

    await page.goto(`/keuangan/pembayaran/baru?invoice=${run.invoice ?? ''}`);
    await page.getByLabel(/^Jumlah/).fill('500000');
    await page.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    await page.getByRole('combobox', { name: /^Bukti/ }).selectOption({ index: 1 });
    await record(page);

    const warning = page.locator('[data-slot="duplicate-warning"]');
    await expect(warning).toContainText('Kemungkinan catatan ganda');
    await expect(warning).toContainText('Rp500.000');
    await warning.getByRole('checkbox').first().check();
    await warning.getByRole('textbox').fill('Dua transfer yang berbeda');
    await record(page);
    await expect(page).toHaveURL(/tercatat=/);
});

test('applies money left unapplied, moves an application and contras the payment in place, both records visible (INT-252; INT-253; INT-254; CM-19; CM-21)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `bayar-koreksi-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '400000' });

    await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
    await page.getByRole('table', { name: 'Pembayaran' }).getByRole('button').first().click();
    await page.getByTestId('payment').getByRole('button', { name: 'Terapkan' }).click();
    const apply = page.getByRole('dialog', { name: 'Terapkan' });
    await apply.getByLabel(/sisa Rp1\.000\.000/).fill('400000');
    await apply.getByRole('button', { name: 'Terapkan' }).click();
    await expect(page.locator('#konten').getByText('Penerapan tercatat.')).toBeVisible();

    await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
    await page.getByRole('table', { name: 'Pembayaran' }).getByRole('button').first().click();
    await expect(page.getByTestId('payment-application')).toContainText('Rp400.000');
    await page.getByTestId('payment').getByRole('button', { name: 'Batalkan Catatan Pembayaran (Kontra)' }).click();
    const contra = page.getByRole('dialog', { name: 'Batalkan Catatan Pembayaran (Kontra)' });
    await expect(contra).toContainText('Rp400.000');
    await contra.getByRole('combobox').first().selectOption('RELEASE');
    await contra.getByLabel(/^Alasan/).fill('Salah catat jumlah');
    await contra.getByRole('button', { name: 'Batalkan Catatan Pembayaran (Kontra)' }).click();
    await expect(page.locator('#konten').getByText(/Kontra tercatat/)).toBeVisible();

    await page.getByRole('table', { name: 'Pembayaran' }).getByRole('button').first().click();
    await expect(page.getByTestId('payment').locator('[data-status="payment.CONTRA_D"]')).toBeVisible();
    await expect(page.getByTestId('payment-application')).toContainText('Dilepas karena pembayaran dikontra');
    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(page.locator('#konten')).toContainText('Rp1.000.000');
});

test('records a statement line with its statement, a flagged payment citing it, a mistyped line voided with its reason (J-FIN-07; QS-12; INT-261; INT-262; criterion 10)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `mutasi-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '250000' });

    await page.goto(`/keuangan/mutasi-bank?perusahaan=${run.company}&tab=belum`);
    await expect(page.getByRole('table', { name: 'Mutasi Bank' })).toContainText('Rp250.000');

    await page.goto(`/keuangan/mutasi-bank?perusahaan=${run.company}`);
    await page.getByRole('button', { name: 'Catat Mutasi Bank' }).click();
    const sheet = page.getByRole('dialog', { name: 'Catat Mutasi Bank' });
    await sheet.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    await sheet.getByLabel(/^Rekening koran/).selectOption({ index: 1 });
    await sheet.getByLabel(/^Jumlah/).fill('250000');
    await sheet.getByLabel(/^Referensi baris/).fill('TRX-UJI-1');
    await sheet.getByRole('checkbox').first().check();
    await sheet.getByRole('button', { name: 'Catat Mutasi Bank' }).click();
    await expect(page.getByRole('table', { name: 'Mutasi Bank' })).toContainText('TRX-UJI-1');
    await expect(page.getByRole('table', { name: 'Mutasi Bank' })).toContainText('Rp250.000 dikutip');

    await page.getByRole('button', { name: 'Catat Mutasi Bank' }).click();
    const second = page.getByRole('dialog', { name: 'Catat Mutasi Bank' });
    await second.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    await second.getByLabel(/^Rekening koran/).selectOption({ index: 1 });
    await second.getByLabel(/^Jumlah/).fill('999');
    await second.getByLabel(/^Referensi baris/).fill('TRX-SALAH');
    await second.getByRole('button', { name: 'Catat Mutasi Bank' }).click();
    const row = page.getByRole('row', { name: /TRX-SALAH/ });
    await row.getByRole('button').last().click();
    await page.getByRole('menuitem', { name: 'Batalkan Baris' }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByRole('textbox').fill('Salah ketik jumlah');
    await dialog.getByRole('button', { name: 'Simpan Koreksi' }).click();
    await expect(page.getByRole('table', { name: 'Mutasi Bank' })).toContainText('Dibatalkan: Salah ketik jumlah');
});

test('answers CJ-21\'s lost answer by retrying the same identifier, one payment recorded (CJ-21; CI-05; IP-04; M-10)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `bayar-hilang-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true });

    await page.goto(`/keuangan/pembayaran/baru?invoice=${run.invoice ?? ''}`);
    await page.getByLabel(/^Jumlah/).fill('150000');
    await page.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    await page.getByRole('combobox', { name: /^Bukti/ }).selectOption({ index: 1 });

    let dropped = false;
    await page.route('**/keuangan/pembayaran', async (route) => {
        if (!dropped && route.request().method() === 'POST') {
            dropped = true;
            // The server commits; only the answer is lost on the way back.
            await route.fetch();
            await route.abort('connectionreset');

            return;
        }

        await route.continue();
    });
    await record(page);

    await expect(page.getByRole('button', { name: 'Coba Lagi' })).toBeVisible();
    await page.getByRole('button', { name: 'Coba Lagi' }).click();
    await expect(page).toHaveURL(/tercatat=/);
    await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
    // A header row and the one payment.
    await expect(page.getByRole('table', { name: 'Pembayaran' }).getByRole('row')).toHaveCount(2);
});
