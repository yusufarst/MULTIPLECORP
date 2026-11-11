import { expect as base, type Locator, type Page, test } from '@playwright/test';

import { siplahRun } from '../journey-c-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-062; CJ-08; J-PRJ-01's SIPLAH channel; J-FIN-04; AC-15; IC-14; BR-FIN-09; BR-FIN-10; FS-13; FX-01 6;
// criteria 6, 7, 8): a SIPLAH-channel project created with its references and values as entered, confirmed on the
// SIPLAH order, invoiced at its gross 10,000,000 and billed, the treasurer's 9,700,000 recorded as the Cash-In applied
// with the 300,000 fee the client claims, and that fee settled as a deduction on SIPLAH's fee advice — gross unchanged,
// Cash-In 9,700,000, fee 300,000, outstanding 0 —, no SIPLAH value computed anywhere; its console and network captured
// and ReconcileGuards clean at its end.

// One journey crosses PG-013, PG-011, PG-042, PG-043, PG-046, PG-045 and PG-047 with seven committed commands.
test.describe.configure({ timeout: 300_000 });

const PROJECT = /\/proyek\/[0-9a-f-]{36}$/;
const INVOICE = /\/keuangan\/invoice\/[0-9a-f-]{36}$/;

/** One labelled figure of a definition list: the row whose term is exactly the label, its value. */
function figure(page: Page, list: Locator, label: string): Locator {
    return list.locator(':scope > div').filter({ has: page.locator('dt', { hasText: new RegExp(`^${label}$`) }) }).locator('dd');
}

/** PG-011's *Data SIPLAH* as entered (IC-14): its references and values, every value not entered left empty — none computed. */
async function siplahAsEntered(page: Page, reference: string): Promise<void> {
    const data = page.locator('[data-slot="facts"]').filter({ hasText: 'Referensi SIPLAH' });
    await expect(figure(page, data, 'Referensi SIPLAH')).toHaveText(reference);
    await expect(figure(page, data, 'Kode program')).toHaveText('PRG-05');
    await expect(figure(page, data, 'Kode akun')).toHaveText('5.2.02');
    await expect(figure(page, data, 'Nilai SIPLAH')).toHaveText('Rp10.000.000');
    await expect(figure(page, data, 'Biaya')).toHaveText('Rp300.000');

    for (const label of ['Nilai pembelian riil', 'Biaya VA', 'Cashback', 'Refund']) {
        await expect(figure(page, data, label), `${label} is never computed (BR-FIN-10)`).toHaveText('—');
    }

    await expect(page.locator('#konten')).toContainText('Nilai SIPLAH dicatat apa adanya sebagai informasi; tidak dihitung dan tidak memindahkan uang.');
}

test('CJ-08: a SIPLAH project\'s references as entered and its net settlement as a deduction on evidence — Cash-In 9,700,000, fee 300,000, outstanding 0 (J-FIN-04; AC-15; FX-01 6; criterion 6)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await siplahRun(page, `cj08-${String(Date.now())}`);
    const reference = `SPL-${run.code}-0077`;
    const position = (label: string) => figure(page, page.getByTestId('position'), label);

    // J-PRJ-01 on the SIPLAH channel: the references and the SIPLAH order's values entered as recorded metadata.
    await page.goto('/proyek/baru');
    await page.getByRole('combobox', { name: /^Perusahaan/ }).selectOption(run.company);
    await page.getByRole('combobox', { name: 'Klien' }).fill(run.client);
    await page.getByRole('option', { name: run.client }).click();
    await page.getByLabel(/^Unit/).selectOption({ label: run.unit });
    await page.getByLabel(/^PIC/).selectOption({ index: 1 });
    await page.getByLabel(/^Judul proyek/).fill('Pengadaan sekolah lewat SIPLAH');
    await page.getByRole('radio', { name: 'SIPLAH' }).click();
    await page.getByLabel(/^Referensi SIPLAH/).fill(reference);
    await page.getByLabel(/^Kode program/).fill('PRG-05');
    await page.getByLabel(/^Kode akun/).fill('5.2.02');
    await page.getByLabel(/^Nilai SIPLAH/).fill('10000000');
    await page.getByLabel('Biaya', { exact: true }).fill('300000');
    await page.getByRole('combobox', { name: 'Produk/Jasa' }).last().fill(run.service);
    await page.getByRole('option', { name: run.service }).click();
    await expect(page.getByRole('combobox', { name: 'Satuan' }).first()).toBeEnabled();
    await page.getByRole('textbox', { name: 'Jumlah' }).first().fill('40');
    await page.getByRole('button', { name: 'Buat Proyek' }).click();
    await page.waitForURL(PROJECT);
    const project = new URL(page.url()).pathname.split('/').pop() ?? '';
    await expect(page.getByText('Proyek dibuat dan nomornya ditetapkan.')).toBeVisible();
    await siplahAsEntered(page, reference);

    // Confirmed on the SIPLAH order (J-PRJ-02's no-quotation path; IP-08).
    await page.getByRole('button', { name: 'Konfirmasi Proyek' }).click();
    const confirmation = page.getByRole('alertdialog');
    await expect(confirmation).toContainText('Pesanan SIPLAH');
    await confirmation.getByRole('button', { name: 'Konfirmasi Proyek' }).click();
    await expect(page.locator('#konten').getByText('Proyek dikonfirmasi. Baris dan harganya sudah dikunci.')).toBeVisible();

    // J-FIN-01: the invoice at the gross 10,000,000, issued with no receivable active yet.
    await page.goto(`/keuangan/invoice/baru?proyek=${project}`);
    await page.getByLabel(/^Keterangan/).fill('Pengadaan sekolah lewat SIPLAH');
    await page.getByRole('textbox', { name: /^Nilai/ }).fill('10000000');
    await expect(page.getByRole('region', { name: /^Pratinjau Invoice/ })).toContainText('Rp10.000.000');
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.locator('#konten').getByText('Draf invoice tersimpan.')).toBeVisible();
    await page.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    const issue = page.getByRole('alertdialog');
    await expect(issue).toContainText('belum ada piutang aktif');
    await issue.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await expect(page.locator('#konten').getByText(/Invoice UJI\/INV\/.* sudah terbit/)).toBeVisible();
    await page.getByRole('link', { name: 'Buka Invoice' }).click();
    await page.waitForURL(INVOICE);
    const invoice = new URL(page.url()).pathname.split('/').pop() ?? '';

    // J-FIN-02: "Tagihkan" makes the gross the active receivable.
    await expect(page.getByTestId('billing')).toContainText('Belum ditagihkan');
    await page.getByRole('button', { name: 'Tagihkan' }).click();
    const bill = page.getByRole('alertdialog');
    await expect(bill).toContainText('Rp10.000.000 menjadi piutang aktif');
    await bill.getByRole('textbox', { name: /^Tanggal jatuh tempo/ }).fill('31/12/2099');
    await bill.getByRole('button', { name: 'Tagihkan' }).click();
    await expect(page.locator('#konten').getByText('Invoice ditagihkan. Sisa tagihannya kini piutang aktif.')).toBeVisible();
    await expect(position('Sisa tagihan')).toHaveText('Rp10.000.000');

    // J-FIN-03: the money received, 9,700,000, applied — the client's 300,000 fee only claimed, never money (BR-FIN-09).
    await page.goto(`/keuangan/pembayaran/baru?invoice=${invoice}`);
    await page.getByLabel(/^Jumlah/).fill('9700000');
    await page.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    const proof = page.getByRole('combobox', { name: /^Bukti/ });
    await proof.selectOption({ value: (await proof.locator('option', { hasText: `Bank Uji ${run.code}` }).getAttribute('value')) ?? '' });
    await page.getByTestId('application').getByLabel(/^Diterapkan/).fill('9700000');
    await page.getByRole('button', { name: 'Klien membayar setelah potongan' }).click();
    // The disclosure's panel reveals its body by animating its height from 0 (GF-42): its button is pressed once it is fully
    // shown, as a person would — pressed while still clipped, the press fell on the trigger above it (WebKit, run 43012262376).
    const addClaim = page.getByRole('button', { name: 'Tambah Potongan' });
    await addClaim.scrollIntoViewIfNeeded();
    await expect(addClaim).toBeInViewport({ ratio: 1 });
    await addClaim.click();
    await page.getByLabel('Nama biaya').fill('BIAYA_SIPLAH');
    await page.getByLabel(/^Jumlah/).last().fill('300000');
    await expect(page.getByRole('alertdialog')).toHaveCount(0);
    await page.getByRole('button', { name: 'Catat Pembayaran' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Pembayaran' }).click();
    await expect(page).toHaveURL(/\/keuangan\/pembayaran\?.*tercatat=/);
    await expect(page.getByTestId('payment')).toContainText('Rp9.700.000');
    await expect(page.getByTestId('payment-application')).toContainText('Rp9.700.000');

    // J-FIN-04: the fee settled as a deduction on SIPLAH's fee advice, its amount entered (AX-21; QS-13).
    await page.goto(`/keuangan/potongan?perusahaan=${run.company}`);
    const waiting = page.getByTestId('deduction-waiting');
    await expect(waiting).toContainText('Rp300.000');
    await waiting.getByRole('button', { name: 'Catat Potongan' }).click();
    const form = page.getByRole('dialog', { name: 'Catat Potongan' });
    await expect(form).toContainText('Tanpa bukti tidak ada potongan');
    await expect(form.getByLabel(/^Jenis potongan/).locator('option').nth(1)).toContainText('BIAYA_SIPLAH');
    await form.getByLabel(/^Jenis potongan/).selectOption({ index: 1 });
    await form.getByLabel(/^Invoice/).selectOption({ index: 1 });
    await form.getByLabel(/^Jumlah/).fill('300000');
    await form.getByLabel(/^Nota biaya/).selectOption({ index: 1 });
    await form.getByRole('button', { name: 'Catat Potongan' }).click();
    const settle = page.getByRole('alertdialog');
    await expect(settle).toContainText('Potongan Rp300.000 menurunkan piutang invoice, bukan kas masuk.');
    await settle.getByLabel(/^Alasan/).fill('Nota biaya SIPLAH terverifikasi');
    await settle.getByRole('button', { name: 'Catat Potongan' }).click();
    const deductions = page.getByRole('table', { name: 'Potongan' });
    await expect(deductions).toContainText('BIAYA_SIPLAH');
    await expect(deductions).toContainText('Rp300.000');
    await expect(deductions).toContainText('Aktif');
    await expect(page.getByText('Tidak ada potongan yang menunggu bukti.')).toBeVisible();

    // FX-01 6 exactly: gross unchanged, Cash-In 9,700,000 applied, fee 300,000 settled, outstanding 0.
    await page.goto(`/keuangan/invoice/${invoice}`);
    await expect(position('Nilai tagihan')).toHaveText('Rp10.000.000');
    await expect(position('Pembayaran diterapkan')).toHaveText('Rp9.700.000');
    await expect(position('Potongan biaya')).toHaveText('Rp300.000');
    await expect(position('Sisa tagihan')).toHaveText('Rp0');

    // The fee is no money: one payment of the company, the 9,700,000 received.
    await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
    const payments = page.getByRole('table', { name: 'Pembayaran' });
    await expect(payments.getByRole('row')).toHaveCount(2);
    await expect(payments).toContainText('Rp9.700.000');

    // The SIPLAH data is still what was entered: nothing computed by the settlement (IC-14; FS-13).
    await page.goto(`/proyek/${project}`);
    await siplahAsEntered(page, reference);

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});
