import { expect as base, type Page, test } from '@playwright/test';

import { signedIn } from '../account-fixtures';
import { invoiceRun } from '../invoice-fixtures';
import {
    bill,
    confirmPayment,
    correctionRun,
    figure,
    fillPayment,
    type Invoice,
    invoiceAdmin,
    openedInvoice,
    payFromInvoice,
    paymentPosition,
    periodRun,
    position,
    recorded,
    rupiah,
} from '../journey-b1-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { caseSettled } from '../return-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-063; CJ-06; AC-12; BR-CR-01–BR-CR-06; BR-DT-03; J-PUR-03; J-FUL-05; UXS-14; UXS-29; criteria 2, 7, 8): on the desktop,
// corrections and voids on companies made for the run, each starting from its record with its reason and linkage and leaving the original
// and the correction both visible — a wrong condition change reversed on PG-020 (CM-28), a delivered unit taken back from the client on
// PG-034 (J-FUL-05) and the invoice revised down on that return's case keeping its payment, a unit returned to its supplier on PG-028
// with its unrecovered share and its case closed (J-PUR-03); a billed invoice carrying a payment and a fee settlement voided and replaced
// in one action with the disposition of each reduction (CM-24; ST-07); and an Admin's backdated invoice — the mark "Tanggal Mundur" on
// the form, MSG-17 for a period that is not live and has no counter, the Owner setting that period's first number, the issue then drawing
// it, the business date beside the recorded time on the record's timeline (UXS-14; UXS-29; NM-03; NM-05). Each test watches its console
// and network from its first step and ends with ReconcileGuards reporting no discrepancy for its companies (TS-11).

// A journey crosses PG-020, PG-042, PG-043, PG-046, PG-030, PG-034, PG-028, PG-045, PG-061 and PG-063 after its fixtures.
test.describe.configure({ timeout: 300_000 });

const DESK = { width: 1280, height: 900 };

/** PG-043 of an invoice by its identifier. */
async function openInvoice(page: Page, invoice: Invoice): Promise<void> {
    await page.goto(`/keuangan/invoice/${invoice.id}`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Invoice ${invoice.number}`);
}

/** PG-043's position, its applied money and what is left. */
async function owing(page: Page, applied: number, outstanding: number): Promise<void> {
    await expect(figure(position(page), 'Pembayaran diterapkan')).toHaveText(rupiah(applied));
    await expect(figure(position(page), 'Sisa tagihan')).toHaveText(rupiah(outstanding));
}

/** PG-063 on one invoice's own timeline (IX-13): the event with this sentence, carrying `text` (BR-XC-01). */
async function onTimeline(page: Page, invoice: Invoice, sentence: string, text: string): Promise<void> {
    await page.goto(`/aktivitas/riwayat?catatan=invoice:${invoice.id}`);
    await expect(page.locator('#konten')).toContainText('Invoice tertentu');
    await expect(page.getByRole('table', { name: 'Riwayat Aktivitas' }).getByRole('row').filter({ hasText: sentence }).first()).toContainText(text);
}

test('CJ-06 at the warehouse: a condition change reversed, a delivered unit returned by the client and its invoice revised down on that case keeping its payment, a unit returned to the supplier and its case closed — originals and corrections both visible (AC-12; CM-28; J-FUL-05; J-PUR-03; criterion 2)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = await correctionRun(page, `cj06-gudang-${String(Date.now())}`);
    const content = page.locator('#konten');

    // CM-28 on PG-020: the wrong condition change reversed from its record with its reason; both movements linked, no second reversal.
    await page.goto(`/gudang/riwayat-stok?produk=${run.product}&jenis=CONDITION_CHANGE`);
    await page.getByRole('button', { name: 'Balikkan', exact: true }).first().click();
    const reversing = page.getByRole('alertdialog', { name: 'Balikkan pergerakan ini?' });
    await expect(reversing).toContainText('Keduanya tetap terlihat di riwayat');
    const confirmReversal = reversing.getByRole('button', { name: 'Balikkan', exact: true });
    await expect(confirmReversal).toBeDisabled();
    await reversing.getByRole('textbox').last().fill('Kondisi dua unit keliru dicatat');
    await confirmReversal.click();
    await expect(page.getByText('Pergerakan dibalikkan.')).toBeVisible();
    await page.goto(`/gudang/riwayat-stok?produk=${run.product}`);
    await expect(page.locator('[data-slot="corrected-by"]').first()).toBeVisible();
    await expect(page.locator('[data-slot="corrects"]').first()).toBeVisible();
    await expect(content).toContainText('Kondisi dua unit keliru dicatat');
    await page.goto(`/gudang/riwayat-stok?produk=${run.product}&jenis=CONDITION_CHANGE`);
    await expect(page.getByRole('button', { name: 'Balikkan', exact: true })).toHaveCount(0);

    // J-FIN-01 for the three delivered units: Rp750,000 from the confirmed line, issued, billed and paid 300,000.
    await page.goto(`/keuangan/invoice/baru?proyek=${run.project}`);
    await expect(page.getByRole('heading', { name: new RegExp(`Invoice baru — ${run.project_number}`) })).toBeVisible();
    await page.getByRole('radio', { name: 'Dari baris proyek' }).click();
    await page.getByTestId('invoice-line').getByRole('textbox').fill('3');
    const paper = page.getByRole('region', { name: /^Pratinjau Invoice/ });
    await expect(paper).toContainText('DRAF — belum terbit');
    await expect(paper).toContainText(rupiah(750_000));
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(content.getByText('Draf invoice tersimpan.')).toBeVisible();
    await page.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await expect(content.getByText(/Invoice UJI\/INV\/\d{4}\/\d{4} sudah terbit/)).toBeVisible();
    await page.getByRole('link', { name: 'Buka Invoice' }).click();
    const invoice = await openedInvoice(page);
    await expect(figure(position(page), 'Nilai penjualan')).toHaveText(rupiah(750_000));
    await bill(page, 750_000);
    await payFromInvoice(page);
    await fillPayment(page, run.code, 300_000, [{ invoice, amount: 300_000 }]);
    await confirmPayment(page);
    await recorded(page, 300_000);
    await openInvoice(page, invoice);
    await owing(page, 300_000, 450_000);

    // J-FUL-05 from PG-030's delivered record: one unit back into usable stock with its proof and reason, its case opened; the delivery
    // record stays and the net delivered falls (AX-10).
    await page.goto('/gudang/pengiriman?tab=tercatat');
    await page.locator('[data-slot="delivery-row"]').filter({ hasText: run.project_number }).first().getByRole('link', { name: 'Retur dari Klien' }).click();
    await page.waitForURL(new RegExp(`/proyek/${run.project_id}/retur$`));
    const form = page.locator('[data-slot="return-form"]');
    await page.locator('[data-slot="return-unit"]').first().getByLabel(/^Jumlah kembali/).fill('1');
    await form.getByLabel('Rak tujuan').selectOption({ value: run.rack_handle });
    await form.getByLabel('Bukti retur').selectOption({ value: run.return_proof });
    await form.getByRole('button', { name: 'Catat Retur dari Klien', exact: true }).click();
    const returning = page.getByRole('alertdialog');
    const returnReason = 'Klien mengembalikan satu unit';
    await returning.getByLabel('Alasan').fill(returnReason);
    await returning.getByRole('button', { name: 'Catat Retur dari Klien', exact: true }).click();
    await expect(content).toContainText('Retur dari klien dicatat; kasusnya dibuka.');
    await expect(page.locator('[data-slot="return-line"]').first()).toContainText('diterima bersih 2, kembali 1');
    const salesCase = page.locator('[data-slot="return-case"]').filter({ hasText: returnReason });
    await expect(salesCase).toContainText('Terbuka');

    // CM-24: the invoice revised down to the two units kept, its basis the return's case, its 300,000 payment kept on it (ST-07).
    await openInvoice(page, invoice);
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Revisi Turun' }).click();
    const revising = page.getByRole('alertdialog');
    await expect(revising).toContainText('Versi lama tetap tercatat. Semua pembayaran dan potongan di bawah ini harus ditentukan nasibnya sekarang.');
    await revising.getByLabel(/^Dasar koreksi/).selectOption('RETURN');
    const basis = revising.getByLabel(/^Catatan dasar/);
    await expect(basis.locator('option').filter({ hasText: 'SALES_RETURN' })).toHaveCount(1);
    await basis.selectOption({ index: 1 });
    await revising.getByLabel(/^Nilai baru/).fill('500000');
    await revising.getByLabel(/^Keterangan/).fill('Dua unit setelah retur klien');
    await revising.getByLabel(/^Pembayaran /).selectOption('KEEP');
    const revisionReason = 'Satu unit dikembalikan klien';
    await revising.getByRole('textbox').last().fill(revisionReason);
    await revising.getByRole('button', { name: 'Revisi Turun' }).click();
    await expect(content.getByText('Invoice direvisi turun.')).toBeVisible();
    await expect(figure(position(page), 'Nilai penjualan')).toHaveText(rupiah(500_000));
    await owing(page, 300_000, 200_000);
    await expect(page.getByTestId('reductions')).toContainText(rupiah(300_000));
    await onTimeline(page, invoice, 'Menerbitkan invoice', 'Admin');
    await onTimeline(page, invoice, 'Merevisi turun invoice', revisionReason);

    // J-PUR-03 on PG-028 from the lot's purchase: one usable unit returned to the supplier with its reason, the outcome its unrecovered
    // share at the unit's net cost, the case balanced and closed (AX-11; NX-16; C-46).
    await page.goto(`/gudang/retur-pemasok?pembelian=${run.purchase_of_lot}`);
    const lot = page.locator('[data-slot="return-form"]').getByLabel('Lot yang diretur');
    await lot.selectOption({ value: (await lot.locator('option', { hasText: run.lot }).getAttribute('value')) ?? '' });
    await page.waitForURL(/lot=/);
    await page.locator('[data-slot="return-place"]').first().waitFor();
    await page.locator('[data-slot="return-place"]').filter({ hasText: 'Layak pakai' }).first().getByLabel(/Jumlah dari/).fill('1');
    await page.locator('[data-slot="return-form"]').getByRole('button', { name: 'Retur ke Pemasok', exact: true }).click();
    await page.getByRole('alertdialog').getByLabel('Alasan').fill('Rusak dari pemasok');
    const returned = caseSettled(page);
    await page.getByRole('alertdialog').getByRole('button', { name: 'Retur ke Pemasok', exact: true }).click();
    await page.waitForURL(/kasus=/);
    await returned;
    await expect(content).toContainText('Retur ke pemasok tercatat.');
    await expect(page.locator('[data-slot="return-open"]')).toBeVisible();
    const outcome = page.locator('[data-slot="return-outcome"]');
    await outcome.getByLabel('Hasil', { exact: true }).selectOption('UNRECOVERED');
    // Indonesian entry: a decimal comma, the point grouping thousands.
    await outcome.getByLabel('Nilai', { exact: true }).fill(run.unit_cost.replace('.', ','));
    await outcome.getByRole('button', { name: 'Catat Hasil' }).click();
    await page.getByRole('alertdialog').getByLabel('Alasan').fill('Pemasok menolak mengganti');
    const settled = caseSettled(page);
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Hasil' }).click();
    await settled;
    await expect(content).toContainText('Hasil retur tercatat.');
    await expect(page.locator('[data-slot="return-balanced"]')).toBeVisible();
    await page.locator('[data-slot="return-case"]').getByRole('button', { name: 'Tutup Kasus' }).click();
    await page.getByRole('alertdialog').getByLabel('Alasan').fill('Retur ke pemasok selesai');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Tutup Kasus' }).click();
    await expect(page.locator('[data-slot="return-case"]')).toContainText('Ditutup');
    await expect(page.locator('[data-slot="return-case"]').getByRole('button', { name: 'Tutup Kasus' })).toHaveCount(0);

    // The client's return still stands beside its delivery: the case open, one unit back.
    await page.goto(`/proyek/${run.project_id}/retur`);
    await expect(page.locator('[data-slot="return-line"]').first()).toContainText('kembali 1');
    await expect(page.locator('[data-slot="return-case"]').filter({ hasText: returnReason })).toBeVisible();

    await guardsClean(page, testInfo, [run.company, run.other]);
    await clean(captured, testInfo);
});

test('CJ-06 void: a billed invoice carrying a payment and a fee settlement voided and replaced in one action, each reduction disposed, the original and its replacement both visible (AC-12; CM-24; ST-07; BR-CR-03; criterion 2)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    // TASK-049's issued and billed invoice of Rp1,000,000, a payment of 600,000 applied and a bank fee of 100,000 settled on its advice.
    const run = await invoiceRun(page, `cj06-batal-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '600000', apply: true, claim: '100000', proven: '100000', settled: '100000' });
    const content = page.locator('#konten');
    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    const original = await openedInvoice(page);
    await expect(figure(position(page), 'Nilai tagihan')).toHaveText(rupiah(1_000_000));
    await expect(figure(position(page), 'Potongan biaya')).toHaveText(rupiah(100_000));
    await owing(page, 600_000, 300_000);
    const reductions = page.getByTestId('reductions');
    await expect(reductions).toContainText(rupiah(600_000));
    await expect(reductions).toContainText(rupiah(100_000));

    // CM-24: "Batalkan Invoice" naming its basis — an entry error with its evidence —, its replacement of Rp900,000 in the same action,
    // and the disposition of every reduction: the payment moved to the replacement, the settlement recorded again on it.
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Batalkan Invoice' }).click();
    const voiding = page.getByRole('alertdialog');
    await voiding.getByLabel(/^Dasar koreksi/).selectOption('ENTRY_ERROR_REPLACED');
    await voiding.getByLabel(/^Bukti salah catat/).selectOption({ index: 1 });
    await voiding.getByLabel(/^Nilai invoice pengganti/).fill('900000');
    await voiding.getByLabel(/^Keterangan/).fill('Instalasi, nilai yang benar');
    await expect(voiding.getByText('Yang terjadi dengan ini')).toBeVisible();
    await voiding.getByLabel(/^Pembayaran /).selectOption('RETARGET');
    await voiding.getByLabel(/^Potongan biaya /).selectOption('RERECORD');
    const voidReason = 'Nilai invoice salah catat, diganti Rp900.000';
    await voiding.getByRole('textbox').last().fill(voidReason);
    await voiding.getByRole('button', { name: 'Batalkan Invoice' }).click();
    await expect(content.getByText('Invoice dibatalkan.')).toBeVisible();
    await expect(content).toContainText(`Dibatalkan: ${voidReason}`);
    await expect(content).toContainText('Dibatalkan');
    await owing(page, 0, 0);

    // The replacement: Rp900,000, the payment and the settlement on it, the billing act inherited with its dates.
    await page.getByRole('link', { name: 'Diganti oleh invoice lain' }).click();
    const replacement = await openedInvoice(page);
    expect(replacement.number).not.toBe(original.number);
    await expect(content.getByRole('link', { name: 'Mengganti invoice lain' })).toHaveAttribute('href', `/keuangan/invoice/${original.id}`);
    await expect(figure(position(page), 'Nilai penjualan')).toHaveText(rupiah(900_000));
    await expect(figure(position(page), 'Potongan biaya')).toHaveText(rupiah(100_000));
    await owing(page, 600_000, 200_000);
    await expect(page.getByTestId('billing')).toContainText('Tanggal tagihan diwarisi dari invoice yang diganti.');
    await expect(page.getByTestId('billing')).toContainText('31 Desember 2099');

    // The payment keeps its 600,000, now applied to the replacement, the ended application still shown; no money left orphaned.
    await page.goto(`/keuangan/pembayaran?perusahaan=${run.company}`);
    await page.getByRole('table', { name: 'Pembayaran' }).getByRole('row').filter({ hasText: rupiah(600_000) }).getByRole('button').first().click();
    await expect(figure(paymentPosition(page), 'Jumlah')).toHaveText(rupiah(600_000));
    await expect(figure(paymentPosition(page), 'Diterapkan')).toHaveText(rupiah(600_000));
    await expect(figure(paymentPosition(page), 'Kredit Pelanggan')).toHaveText('Rp0');
    const applications = page.getByTestId('payment-application');
    await expect(applications).toHaveCount(2);
    await expect(applications.filter({ hasText: replacement.number })).toContainText(rupiah(600_000));
    await expect(applications.filter({ hasText: original.number })).toHaveCount(1);

    // The original stays readable with its reason; its timeline names the void.
    await onTimeline(page, original, 'Membatalkan invoice', voidReason);
    await openInvoice(page, original);
    await expect(content.getByRole('link', { name: 'Diganti oleh invoice lain' })).toHaveAttribute('href', `/keuangan/invoice/${replacement.id}`);

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

test('CJ-06 backdating: an Admin\'s invoice backdated with its mark, MSG-17 for a period that is not live and has no counter, the Owner setting its first number, the issue then drawing it, the business date on its timeline (UXS-14; UXS-29; BR-DT-03; NM-03; NM-05; criterion 2)', async ({ browser, page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize(DESK);
    const run = await periodRun(page, `cj06-mundur-${String(Date.now())}`);
    const [year, month] = run.period.split('-');

    // The Admin of the run's company — `invoice.issue` and `finance.view` there, nothing of the numbering —, in a context of its own with
    // the same go-live armed.
    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, ignoreHTTPSErrors: true, viewport: DESK, extraHTTPHeaders: { 'X-Fixture-Go-Live': run.goLive } });
    const admin = await context.newPage();
    const capturedAdmin = watch(admin);
    await signedIn(admin, 'cj06-admin', { plain: true });
    await invoiceAdmin(admin, run.company);
    const adminContent = admin.locator('#konten');

    // J-FIN-01 by the Admin: a termin of Rp1,000,000 saved, then its issue dated back to the 20th of the previous month (IP-07).
    await admin.goto(`/keuangan/invoice/baru?proyek=${run.project}`);
    await expect(admin.getByRole('heading', { name: new RegExp(`Invoice baru — ${run.number}`) })).toBeVisible();
    await admin.getByLabel(/^Keterangan/).fill('Termin bulan lalu');
    await admin.getByRole('textbox', { name: /^Nilai/ }).fill('1000000');
    await expect(admin.getByRole('region', { name: /^Pratinjau Invoice/ })).toContainText(rupiah(1_000_000));
    await admin.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(adminContent.getByText('Draf invoice tersimpan.')).toBeVisible();
    await admin.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    const issuing = admin.getByRole('alertdialog');
    await expect(issuing).toContainText('Hari ini');
    await expect(issuing).not.toContainText('Tanggal Mundur');
    await issuing.getByRole('button', { name: 'Ubah' }).click();
    await admin.getByRole('button', { name: 'Bulan sebelumnya' }).click();
    await admin.locator(`[data-day="${run.day}"] button`).click();

    // UXS-14: the mark on the form before sending, and the period the number will follow (NM-05).
    await expect(issuing).toContainText('— dipilih');
    await expect(issuing).toContainText('Tanggal Mundur');
    await base.soft(issuing, 'UXS-14: the backdated form names the period its number follows ("Nomor mengikuti periode {periode}")').toContainText('Nomor mengikuti periode');

    // UXS-29: C-10 for the Admin with MSG-17 naming the period; nothing issued, the draft kept.
    await issuing.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await expect(issuing).toContainText(`Nomor Invoice untuk periode ${run.period}`);
    await expect(issuing).toContainText('Hanya Owner yang dapat memulai penomoran atau menetapkan nomor awal. Hubungi Owner, lalu ulangi tindakan ini.');

    // The Owner sets that period's first number on PG-061, vouching statement (a).
    await page.goto(`/pengaturan/penomoran?perusahaan=${run.company}&jenis=doc-06`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Penomoran Dokumen');
    await page.getByLabel(/^Periode tanpa penghitung/).fill(run.period);
    await page.getByLabel(/^Nomor awal/).fill('41');
    await page.getByRole('button', { name: 'Tetapkan Nomor Awal' }).click();
    const seeding = page.getByRole('alertdialog');
    await expect(seeding).toContainText(`Periode ${run.period} → nomor pertama 41`);
    await expect(seeding).toContainText('Setiap nomor awal lebih besar daripada semua nomor periode tersebut yang pernah diterbitkan di luar sistem ini');
    const seed = seeding.getByRole('button', { name: 'Tetapkan Nomor Awal' });
    await expect(seed).toBeDisabled();
    await seeding.getByRole('checkbox').check();
    await seed.click();
    await expect(page.getByText(/Nomor awal .+ ditetapkan\./)).toBeVisible();
    const counter = page.getByRole('row').filter({ has: page.getByRole('cell', { name: run.period, exact: true }) });
    await expect(counter).toContainText('41');

    // The Admin's same issue again, the date kept: it draws the period's first number.
    await issuing.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await expect(issuing).toBeHidden();
    const number = `UJI/INV/${year ?? ''}/${month ?? ''}/0041`;
    await expect(adminContent.getByText(`Invoice ${number} sudah terbit.`)).toBeVisible();
    await admin.getByRole('link', { name: 'Buka Invoice' }).click();
    const invoice = await openedInvoice(admin);
    expect(invoice.number).toBe(number);
    await expect(adminContent).toContainText('Belum Ditagihkan');

    // The counter moved on; the record's timeline keeps the recorded time with the business date beside it (BR-DT-03; BR-DT-05).
    await page.reload();
    await expect(counter).toContainText('42');
    await onTimeline(page, invoice, 'Menerbitkan invoice', 'Tanggal transaksi');

    await guardsClean(page, testInfo, [run.company]);
    await clean({ ...captured, console: [...captured.console, ...capturedAdmin.console], failed: [...captured.failed, ...capturedAdmin.failed], csp: [...captured.csp, ...capturedAdmin.csp], requests: captured.requests + capturedAdmin.requests }, testInfo);
    await context.close();
});
