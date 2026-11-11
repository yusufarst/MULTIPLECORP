import { expect as base, type Locator, type Page, test } from '@playwright/test';

import {
    bill,
    confirmPayment,
    figure,
    financeRun,
    type FinanceRun,
    fillPayment,
    type Invoice,
    issueValueInvoice,
    openedInvoice,
    payFromInvoice,
    paymentPosition,
    position,
    recorded,
    rupiah,
} from '../journey-b1-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-063; CJ-04; AC-10; BR-FIN-01–BR-FIN-06; BR-CR-03; BR-DOC-04; J-FIN-01, J-FIN-03, J-FIN-05, J-FIN-06, J-FIN-07, J-FIN-08;
// UXS-16; UXS-38; criteria 1, 7, 8): the Owner, on a company and a confirmed project of Rp5,000,000 made for the run, issues and bills
// value-only DP and termin invoices on PG-042 and PG-043 and records the money on PG-046 — the desktop and the phone at 390 px —; every
// figure is the numeric annex's (FX-01 1–5): the AC-10 baseline 1,000,000 → 700,000 → 0, the advance of 400,000 counted once and billing
// activating 600,000 only, the overpayment of 1,200,000 applying 1,000,000 with 200,000 of credit refunded once, the reallocation of
// 400,000 keeping the payment's total, the void after a partial payment disposing of its 400,000. A payment duplicate's warning with its
// override reason (UXS-16), the pre-payment Kuitansi's own state recording no payment until it is linked (UXS-38), the dispute hold and
// the Owner's write-off (J-FIN-05), and other cash (J-FIN-07). Each test watches its console and network from its first step and ends
// with ReconcileGuards reporting no discrepancy for its company (TS-11; TEST_STRATEGY §8, §16).

// A journey crosses PG-042, PG-043, PG-044, PG-045, PG-046, PG-048, PG-036, PG-049 and PG-063 with some twenty committed commands.
test.describe.configure({ timeout: 300_000 });

const DESK = { width: 1280, height: 900 };
const PHONE = { width: 390, height: 844 };

// FX-01 (tests/Fixtures/FX-01.php; BUSINESS_RULES numeric annex 1–5): each example's invoice is Rp1,000,000.
const FX01 = {
    invoice: 1_000_000,
    baseline: { first: 300_000, second: 700_000, afterFirst: 700_000, afterSecond: 0 },
    advance: { paid: 400_000, activated: 600_000 },
    overpayment: { paid: 1_200_000, applied: 1_000_000, credit: 200_000 },
    reallocation: { moved: 400_000 },
    void: { applied: 400_000 },
};

/** PG-043 of an invoice by its identifier. */
async function openInvoice(page: Page, invoice: Invoice): Promise<void> {
    await page.goto(`/keuangan/invoice/${invoice.id}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Invoice ${invoice.number}`);
}

/** PG-043's position, its applied money and what is left, as the annex states them. */
async function owing(page: Page, applied: number, outstanding: number): Promise<void> {
    await expect(figure(position(page), 'Pembayaran diterapkan')).toHaveText(rupiah(applied));
    await expect(figure(position(page), 'Sisa tagihan')).toHaveText(rupiah(outstanding));
}

/** PG-045's payments of the run's company: the header row and one row per payment, each amount listed. */
async function payments(page: Page, run: FinanceRun, amounts: number[]): Promise<Locator> {
    await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
    const table = page.getByRole('table', { name: 'Pembayaran' });
    await expect(table.getByRole('row')).toHaveCount(amounts.length + 1);

    for (const amount of amounts) {
        await expect(table.getByRole('row').filter({ hasText: rupiah(amount) }).first()).toBeVisible();
    }

    return table;
}

/** PG-063 narrowed to the run's company: the newest event with this sentence carries its reason (BR-XC-01). */
async function audited(page: Page, run: FinanceRun, sentence: string, reason: string): Promise<void> {
    await page.goto(`/aktivitas/riwayat?perusahaan=${run.code}`);
    const row = page.getByRole('table', { name: 'Riwayat Aktivitas' }).getByRole('row').filter({ hasText: sentence }).first();
    await expect(row).toContainText(reason);
}

/** PG-049's sheets (other-cash.spec): the branch's options land by a partial reload before the sheet is filled. */
async function recordCash(page: Page, action: string, company: string, fill: (sheet: Locator) => Promise<void>): Promise<void> {
    await page.getByRole('button', { name: action, exact: true }).click();
    const sheet = page.getByRole('dialog');
    const loaded = page.waitForResponse(async (response) => (await response.request().headerValue('x-inertia-partial-data')) === 'form');
    await sheet.getByRole('combobox', { name: 'Cabang' }).selectOption({ value: company });
    await loaded;
    await fill(sheet);
    await sheet.getByRole('button', { name: action, exact: true }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: action, exact: true }).click();
}

test('CJ-04 FX-01 1–3: a termin billed and paid 300,000 on the phone then 700,000, a DP paid in advance and billed for its remainder, an overpayment applied, its credit refunded once (AC-10; J-FIN-01; J-FIN-03; J-FIN-06; criterion 1)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = await financeRun(page, `cj04-annex-${String(Date.now())}`);
    const project = { project: run.project, number: run.number };

    // FX-01 1: issued → sales value 1,000,000, BELUM DITAGIHKAN, no Cash-In, no active receivable.
    const termin = await issueValueInvoice(page, project, 'Termin pertama', FX01.invoice);
    await expect(figure(position(page), 'Nilai tagihan')).toHaveText(rupiah(FX01.invoice));
    await page.goto(`/keuangan/piutang?perusahaan=${run.company}`);
    await expect(page.locator('#konten')).toContainText('Belum ada piutang aktif.');
    await page.goto(`/keuangan/piutang?perusahaan=${run.company}&tab=belum`);
    await expect(page.getByRole('table', { name: 'Piutang' })).toContainText(termin.number);
    await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
    await expect(page.locator('#konten')).toContainText('Belum ada pembayaran.');

    // Billed → outstanding 1,000,000, an active receivable on PG-044.
    await openInvoice(page, termin);
    await bill(page, FX01.invoice);
    await page.goto(`/keuangan/piutang?perusahaan=${run.company}`);
    await expect(page.getByRole('table', { name: 'Piutang' })).toContainText(termin.number);
    await expect(page.getByTestId('subtotals')).toContainText(rupiah(FX01.invoice));

    // J-FIN-03 `[both]`: 300,000 on the phone, its proof and its application to the one invoice; outstanding 700,000.
    await openInvoice(page, termin);
    await payFromInvoice(page);
    await page.setViewportSize(PHONE);
    await fillPayment(page, run.code, FX01.baseline.first, [{ invoice: termin, amount: FX01.baseline.first }]);
    await confirmPayment(page);
    await recorded(page, FX01.baseline.first);
    await page.setViewportSize(DESK);
    await openInvoice(page, termin);
    await owing(page, FX01.baseline.first, FX01.baseline.afterFirst);

    // 700,000 at the desk; outstanding 0.
    await payFromInvoice(page);
    await fillPayment(page, run.code, FX01.baseline.second, [{ invoice: termin, amount: FX01.baseline.second }]);
    await confirmPayment(page);
    await recorded(page, FX01.baseline.second);
    await openInvoice(page, termin);
    await owing(page, FX01.baseline.first + FX01.baseline.second, FX01.baseline.afterSecond);

    // FX-01 2: a DP issued, 400,000 received before billing and applied to the issued-unbilled invoice — Cash-In once —, billing then
    // activating the unpaid 600,000 only (BR-FIN-02; BR-FIN-04).
    const dp = await issueValueInvoice(page, project, 'DP 100% pengadaan', FX01.invoice);
    await payFromInvoice(page);
    await expect(page.getByTestId('application').filter({ hasText: dp.number })).toContainText('Belum ditagihkan');
    await fillPayment(page, run.code, FX01.advance.paid, [{ invoice: dp, amount: FX01.advance.paid }]);
    await confirmPayment(page);
    await recorded(page, FX01.advance.paid);
    await openInvoice(page, dp);
    await expect(page.getByTestId('billing')).toContainText('Belum ditagihkan: belum ada piutang aktif.');
    await expect(figure(position(page), 'Pembayaran diterapkan')).toHaveText(rupiah(FX01.advance.paid));
    await bill(page, FX01.advance.activated);
    await owing(page, FX01.advance.paid, FX01.advance.activated);

    // FX-01 3: 1,200,000 against outstanding 1,000,000 → application 1,000,000, 200,000 unapplied as customer credit, never profit.
    const second = await issueValueInvoice(page, project, 'Termin kedua', FX01.invoice);
    await bill(page, FX01.invoice);
    await payFromInvoice(page);
    await fillPayment(page, run.code, FX01.overpayment.paid, [{ invoice: second, amount: FX01.overpayment.applied }]);
    const form = page.getByRole('region', { name: 'Posisi pembayaran' }).locator('dl');
    await expect(figure(form, 'Kredit Pelanggan')).toHaveText(rupiah(FX01.overpayment.credit));
    await confirmPayment(page);
    await recorded(page, FX01.overpayment.paid);
    await expect(figure(paymentPosition(page), 'Diterapkan')).toHaveText(rupiah(FX01.overpayment.applied));
    await expect(figure(paymentPosition(page), 'Kredit Pelanggan')).toHaveText(rupiah(FX01.overpayment.credit));
    await expect(page.getByTestId('payment-application').filter({ hasText: second.number })).toContainText(rupiah(FX01.overpayment.applied));
    await openInvoice(page, second);
    await owing(page, FX01.overpayment.applied, 0);

    // J-FIN-06: the credit on PG-048 traced to its payment, refunded with the company's transfer proof — one application of 200,000 to a
    // refund disbursement (AX-23; BR-FIN-05).
    await page.goto(`/keuangan/kredit-pelanggan?cabang=${run.company}`);
    const group = page.locator('[data-slot="credit-group"]').filter({ hasText: run.client_name });
    await expect(group).toContainText(`Kredit ${rupiah(FX01.overpayment.credit)}`);
    const source = group.locator('[data-slot="credit-source"]').filter({ hasText: rupiah(FX01.overpayment.credit) });
    await expect(source).toContainText('Pembayaran');
    await source.getByRole('button', { name: 'Kembalikan Dana' }).click();
    const refunding = page.getByRole('alertdialog');
    await expect(refunding).toContainText('Dana keluar dari rekening yang dipilih sebagai pengembalian (Kas Keluar)');
    await refunding.getByLabel('Bukti transfer').selectOption({ value: run.transfer_proof });
    await refunding.getByLabel(/^Alasan/).fill('Klien meminta kelebihan bayar dikembalikan');
    await refunding.getByRole('button', { name: 'Kembalikan Dana', exact: true }).click();
    await expect(page.locator('#konten')).toContainText('Pengembalian dana dicatat.');
    const refund = group.locator('[data-slot="credit-refund"]');
    await expect(refund).toHaveCount(1);
    await expect(refund).toContainText(rupiah(FX01.overpayment.credit));
    await expect(refund).toContainText('Berlaku');
    await expect(group).toContainText('Kredit Rp0');

    // The same 200,000 can never also be applied to an invoice: the payment keeps 1,000,000 applied and no credit left (BR-FIN-03).
    const table = await payments(page, run, [FX01.baseline.first, FX01.baseline.second, FX01.advance.paid, FX01.overpayment.paid]);
    await table.getByRole('row').filter({ hasText: rupiah(FX01.overpayment.paid) }).getByRole('button').first().click();
    await expect(figure(paymentPosition(page), 'Jumlah')).toHaveText(rupiah(FX01.overpayment.paid));
    await expect(figure(paymentPosition(page), 'Diterapkan')).toHaveText(rupiah(FX01.overpayment.applied));
    await expect(figure(paymentPosition(page), 'Kredit Pelanggan')).toHaveText('Rp0');
    await expect(page.getByTestId('payment-application').filter({ hasText: 'Refund' })).toContainText(rupiah(FX01.overpayment.credit));
    await openInvoice(page, second);
    await owing(page, FX01.overpayment.applied, 0);

    // PG-044: only the DP's 600,000 is an active receivable.
    await page.goto(`/keuangan/piutang?perusahaan=${run.company}`);
    await expect(page.getByTestId('subtotals')).toContainText(rupiah(FX01.advance.activated));
    await expect(page.getByRole('table', { name: 'Piutang' }).getByRole('row').filter({ hasText: dp.number })).toContainText(rupiah(FX01.advance.activated));

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

test('CJ-04 FX-01 4–5 with the pre-payment Kuitansi, a payment duplicate, the dispute hold, the write-off and other cash: a reallocation keeping the payment\'s total, a void disposing of its 400,000 (UXS-16; UXS-38; J-FIN-05; J-FIN-07; J-FIN-08; criterion 1)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = await financeRun(page, `cj04-koreksi-${String(Date.now())}`);
    const project = { project: run.project, number: run.number };
    const content = page.locator('#konten');

    const first = await issueValueInvoice(page, project, 'Termin pertama', FX01.invoice);
    await bill(page, FX01.invoice);
    const second = await issueValueInvoice(page, project, 'Termin kedua', FX01.invoice);
    await bill(page, FX01.invoice);

    // UXS-38 (J-FIN-08): the pre-payment Kuitansi of 400,000 issued on PG-036 with its reason, its own state *Menunggu Pembayaran* and the
    // mark "untuk proses pembayaran"; it records no payment — the invoice's position unchanged, no Cash-In (BR-DOC-04; QS-19).
    await openInvoice(page, first);
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Terbitkan Kuitansi untuk Proses Pembayaran' }).click();
    const preparing = page.getByRole('alertdialog');
    await expect(preparing).toContainText('Tidak ada pembayaran yang tercatat dan sisa tagihan tidak berubah.');
    await preparing.getByLabel(/^Jumlah Kuitansi/).fill(String(FX01.reallocation.moved));
    await preparing.getByRole('button', { name: 'Lanjut' }).click();
    await page.waitForURL(/\/dokumen\/[0-9a-f-]{36}/);
    await page.getByRole('button', { name: /^Terbitkan/ }).click();
    const issuing = page.getByRole('alertdialog');
    await expect(issuing).toContainText('Tidak ada pembayaran yang tercatat');
    await issuing.getByRole('textbox').fill('Bendahara meminta Kuitansi untuk proses pembayaran');
    await issuing.getByRole('button', { name: /^Terbitkan/ }).click();
    await expect(content.getByText('Kuitansi terbit. Belum ada pembayaran yang tercatat.')).toBeVisible();
    await expect(content).toContainText('Menunggu Pembayaran');
    await expect(content).toContainText('untuk proses pembayaran');
    await openInvoice(page, first);
    const kuitansi = page.getByTestId('kuitansi');
    await expect(kuitansi).toContainText('Menunggu Pembayaran');
    await expect(kuitansi).toContainText(rupiah(FX01.reallocation.moved));
    await owing(page, 0, FX01.invoice);
    await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
    await expect(content).toContainText('Belum ada pembayaran.');

    // J-FIN-03: 400,000 applied to the first invoice, the form naming its open Kuitansi.
    await openInvoice(page, first);
    await payFromInvoice(page);
    await expect(page.getByTestId('application').filter({ hasText: first.number })).toContainText('Ada Kuitansi menunggu pembayaran: tautkan setelah pembayaran tercatat.');
    await fillPayment(page, run.code, FX01.reallocation.moved, [{ invoice: first, amount: FX01.reallocation.moved }]);
    await confirmPayment(page);
    await recorded(page, FX01.reallocation.moved);

    // FX-01 4: the application of 400,000 moved from the first invoice to the second — a superseding application, the payment's total
    // unchanged, the move with its reason (CM-21; AX-19).
    await page.getByTestId('payment-application').filter({ hasText: first.number }).getByRole('button', { name: 'Pindahkan Penerapan' }).click();
    const moving = page.getByRole('dialog', { name: 'Pindahkan Penerapan' });
    const target = moving.getByLabel(/^Pindahkan ke invoice/);
    const option = target.locator('option').filter({ hasText: second.number });
    await expect(option).toHaveCount(1);
    await target.selectOption({ value: (await option.getAttribute('value')) ?? '' });
    const reallocationReason = 'Transfer ini untuk termin kedua menurut bendahara';
    await moving.getByLabel(/^Alasan/).fill(reallocationReason);
    await moving.getByRole('button', { name: 'Pindahkan Penerapan' }).click();
    await expect(content.getByText('Penerapan dipindahkan; total pembayaran tetap.')).toBeVisible();
    await expect(figure(paymentPosition(page), 'Jumlah')).toHaveText(rupiah(FX01.reallocation.moved));
    await expect(figure(paymentPosition(page), 'Diterapkan')).toHaveText(rupiah(FX01.reallocation.moved));
    await expect(figure(paymentPosition(page), 'Kredit Pelanggan')).toHaveText('Rp0');
    await expect(page.getByTestId('payment-application').filter({ hasText: 'Dipindahkan ke invoice lain' })).toContainText(first.number);
    await openInvoice(page, first);
    await owing(page, 0, FX01.invoice);
    await openInvoice(page, second);
    await owing(page, FX01.reallocation.moved, FX01.invoice - FX01.reallocation.moved);

    // UXS-16 (BD-02; MSG-15): a second 400,000 on the same account within seven days warns with the match; the Owner acknowledges it and
    // gives the reason, and the payment is recorded — the override audited with its reason.
    await openInvoice(page, first);
    await payFromInvoice(page);
    await fillPayment(page, run.code, FX01.reallocation.moved, [{ invoice: first, amount: FX01.reallocation.moved }]);
    await confirmPayment(page);
    const warning = page.locator('[data-slot="duplicate-warning"]');
    await expect(warning).toContainText('Kemungkinan catatan ganda');
    await expect(warning).toContainText(rupiah(FX01.reallocation.moved));
    await expect(page).toHaveURL(/\/keuangan\/pembayaran\/baru\?invoice=/);
    await warning.getByRole('checkbox').first().check();
    const overrideReason = 'Dua transfer berbeda dari bendahara';
    await warning.getByRole('textbox').fill(overrideReason);
    await confirmPayment(page);
    await recorded(page, FX01.reallocation.moved);
    await audited(page, run, 'Mencatat pembayaran meski ada kemungkinan ganda', overrideReason);
    await audited(page, run, 'Memindahkan penerapan', reallocationReason);

    // J-FIN-08: "Tautkan Kuitansi" on PG-043 — the Kuitansi's 400,000 equal to the payment applied to its invoice —, now linked.
    await openInvoice(page, first);
    await kuitansi.getByRole('button', { name: 'Tautkan Kuitansi' }).click();
    const linking = page.getByRole('alertdialog');
    await expect(linking).toContainText(`Jumlah Kuitansi ${rupiah(FX01.reallocation.moved)}`);
    await expect(linking.getByRole('checkbox')).toHaveCount(1);
    await linking.getByRole('checkbox').check();
    await linking.getByRole('button', { name: 'Tautkan Kuitansi' }).click();
    await expect(content.getByText('Kuitansi tertaut ke pembayaran.')).toBeVisible();
    await expect(kuitansi).toContainText('Terhubung ke Pembayaran');
    await owing(page, FX01.reallocation.moved, FX01.invoice - FX01.reallocation.moved);

    // FX-01 5: the second invoice, billed 1,000,000 with 400,000 applied, voided — its basis an entry error with its evidence and the
    // replacement named in the same action, the 400,000 released to the client's credit: no orphaned money (BR-CR-03; CM-24; ST-07).
    await openInvoice(page, second);
    await expect(page.getByTestId('reductions')).toContainText(rupiah(FX01.void.applied));
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Batalkan Invoice' }).click();
    const voiding = page.getByRole('alertdialog');
    await expect(voiding).toContainText('Semua pembayaran dan potongan di bawah ini harus ditentukan nasibnya sekarang.');
    await voiding.getByLabel(/^Dasar koreksi/).selectOption('ENTRY_ERROR_REPLACED');
    await voiding.getByLabel(/^Bukti salah catat/).selectOption({ index: 1 });
    await voiding.getByLabel(/^Nilai invoice pengganti/).fill('900000');
    await voiding.getByLabel(/^Keterangan/).fill('Termin kedua, nilai yang benar');
    await voiding.getByLabel(/^Pembayaran /).selectOption('RELEASE');
    const voidReason = 'Nilai termin kedua salah catat';
    await voiding.getByRole('textbox').last().fill(voidReason);
    await voiding.getByRole('button', { name: 'Batalkan Invoice' }).click();
    await expect(content.getByText('Invoice dibatalkan.')).toBeVisible();
    await expect(content).toContainText(`Dibatalkan: ${voidReason}`);
    await owing(page, 0, 0);
    await page.getByRole('link', { name: 'Diganti oleh invoice lain' }).click();
    const replacement = await openedInvoice(page);
    await expect(content.getByRole('link', { name: 'Mengganti invoice lain' })).toHaveAttribute('href', `/keuangan/invoice/${second.id}`);
    await expect(figure(position(page), 'Nilai penjualan')).toHaveText(rupiah(900_000));
    await owing(page, 0, 900_000);
    expect(replacement.id).not.toBe(second.id);

    // The released 400,000 stands as the client's credit on PG-048, traced to its payment.
    await page.goto(`/keuangan/kredit-pelanggan?cabang=${run.company}`);
    const group = page.locator('[data-slot="credit-group"]').filter({ hasText: run.client_name });
    await expect(group).toContainText(`Kredit ${rupiah(FX01.void.applied)}`);
    await expect(group.locator('[data-slot="credit-source"]').filter({ hasText: rupiah(FX01.void.applied) })).toContainText('Pembayaran');

    // J-FIN-05: the dispute hold with its reason — the receivable still active and ageing —, cleared; then the Owner's write-off of
    // 100,000, the client's credit named first and the reason for not applying it given (L-17; AX-24; DIR-019).
    await openInvoice(page, first);
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Tandai Sengketa' }).click();
    const holding = page.getByRole('alertdialog');
    await expect(holding).toContainText('proyek tidak bisa diselesaikan secara normal');
    await holding.getByRole('textbox').last().fill('Klien membantah termin pertama');
    await holding.getByRole('button', { name: 'Tandai Sengketa' }).click();
    await expect(page.getByTestId('hold')).toContainText('Klien membantah termin pertama');
    await expect(page.getByTestId('hold')).toContainText('Piutang tetap aktif dan umurnya terus berjalan');
    await owing(page, FX01.reallocation.moved, FX01.invoice - FX01.reallocation.moved);
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Cabut Sengketa' }).click();
    const clearing = page.getByRole('alertdialog');
    await clearing.getByRole('textbox').last().fill('Klien sudah setuju');
    await clearing.getByRole('button', { name: 'Cabut Sengketa' }).click();
    await expect(content.getByText('Tanda sengketa dicabut.')).toBeVisible();
    await expect(page.getByTestId('hold')).toHaveCount(0);

    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Hapuskan Piutang' }).click();
    const writing = page.getByRole('alertdialog');
    await expect(writing).toContainText('Tidak ada uang masuk dan tidak ada laba');
    await expect(writing.getByTestId('credit')).toContainText(rupiah(FX01.void.applied));
    await writing.getByRole('textbox', { name: /^Jumlah dihapuskan/ }).fill('100000');
    await writing.getByRole('textbox', { name: /^Alasan kredit tidak diterapkan/ }).fill('Kredit itu untuk termin pengganti');
    await writing.getByRole('textbox').last().fill('Sisa termin tidak dapat ditagih');
    await writing.getByRole('button', { name: 'Hapuskan Piutang' }).click();
    await expect(content.getByText('Piutang dihapuskan sebesar jumlah itu. Riwayatnya tetap tercatat.')).toBeVisible();
    await expect(page.getByTestId('writeoffs')).toContainText(rupiah(100_000));
    await expect(page.getByTestId('writeoffs')).toContainText('Sisa termin tidak dapat ditagih');
    await expect(figure(position(page), 'Dihapuskan')).toHaveText(rupiah(100_000));
    await owing(page, FX01.reallocation.moved, FX01.invoice - FX01.reallocation.moved - 100_000);

    // J-FIN-07 on PG-049: a purchase paid through "Catat Pengeluaran Kas" — the only Cash-Out —, a project expense citing its supplier
    // bill, received cashback as other Cash-In (BR-FIN-10; BR-FIN-11).
    await page.goto(`/keuangan/kas-lain?perusahaan=${run.company}`);
    await expect(page.getByRole('heading', { name: 'Pengeluaran & Kas Lain', level: 1 })).toBeVisible();
    await expect(content).toContainText('Pengeluaran kas adalah satu-satunya Kas Keluar');
    await recordCash(page, 'Catat Pengeluaran Kas', run.company, async (sheet) => {
        await sheet.getByRole('combobox', { name: 'Jenis' }).selectOption({ value: 'PURCHASE_PAYMENT' });
        await sheet.getByLabel('Jumlah').fill('250000');
        await sheet.getByRole('combobox', { name: 'Rekening asal' }).selectOption({ index: 1 });
        await sheet.getByRole('combobox', { name: 'Pembelian' }).selectOption({ value: run.purchase });
        await sheet.getByRole('combobox', { name: 'Bukti' }).selectOption({ value: run.transfer_proof });
    });
    await expect(content).toContainText('Pengeluaran kas dicatat.');
    await expect(content).toContainText(run.purchase_number);
    await recordCash(page, 'Catat Beban', run.company, async (sheet) => {
        await sheet.getByRole('textbox', { name: 'Kategori' }).fill('Sewa alat');
        await sheet.getByLabel('Jumlah').fill('60000');
        await sheet.getByRole('combobox', { name: 'Proyek penyebab' }).selectOption({ value: run.project });
        await sheet.getByRole('combobox', { name: 'Tagihan pemasok yang dikutip' }).selectOption({ value: run.bill });
    });
    await expect(content).toContainText('Beban dicatat.');
    await expect(content).toContainText('Sewa alat');
    await recordCash(page, 'Catat Kas Masuk Lain', run.company, async (sheet) => {
        await sheet.getByRole('combobox', { name: 'Kategori' }).selectOption({ value: 'CASHBACK_INCOME' });
        await sheet.getByLabel('Jumlah').fill('15000');
        await sheet.getByRole('combobox', { name: 'Rekening penerima' }).selectOption({ index: 1 });
        await sheet.getByRole('combobox', { name: 'Bukti' }).selectOption({ value: run.transfer_proof });
    });
    await expect(content).toContainText('Kas masuk dicatat.');
    await expect(content).toContainText('Cashback diterima');

    // The phone reads the first invoice: its position and its linked Kuitansi.
    await page.setViewportSize(PHONE);
    await openInvoice(page, first);
    await owing(page, FX01.reallocation.moved, FX01.invoice - FX01.reallocation.moved - 100_000);
    await expect(page.getByTestId('kuitansi')).toContainText('Terhubung ke Pembayaran');
    await page.setViewportSize(DESK);

    // Cash-In: the two payments of 400,000 only — no Kuitansi, settlement or write-off is money.
    await payments(page, run, [FX01.reallocation.moved, FX01.reallocation.moved]);

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});
