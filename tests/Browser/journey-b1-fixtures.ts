import { expect as base, type Locator, type Page } from '@playwright/test';

import { clientReturnRun, type ClientReturnRun } from './client-return-fixtures';
import { invoiceRun, type InvoiceRun } from './invoice-fixtures';
import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TASK-063's starting preconditions and shared page steps for CJ-04 and CJ-06 (routes/fixtures.php) over the module fixtures —
// master data, numbering, a bank account and the papers that reach the company from outside, never a step the journeys walk —,
// and the steps both journeys take on PG-042, PG-043 and PG-046. Every invoice, billing, payment, credit, refund and correction of
// a journey is made on its pages.

/** The pages' Rupiah (lib/format's money): "Rp", the thousands with dots, no sen. */
export const rupiah = (value: number): string => `Rp${String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;

export const INVOICE_URL = /\/keuangan\/invoice\/[0-9a-f-]{36}$/;

export type Invoice = { id: string; number: string };

/**
 * CJ-04's world: TASK-049's company with go-live armed and the invoice layouts' numbering started, a confirmed service project of two
 * lines of ten at Rp250,000 — Rp5,000,000 to invoice —, the company's bank account with a payment proof issued by "Bank Uji {code}"
 * (InvoiceFixture `bank`), then TASK-054's open purchase, transfer proof and supplier bill of Rp100,000 (OtherCashFixture).
 */
export type FinanceRun = InvoiceRun & { purchase: string; purchase_number: string; transfer_proof: string; bill: string };

export async function financeRun(page: Page, run: string): Promise<FinanceRun> {
    const invoiced = await invoiceRun(page, run, { stage: 'none', bank: true, lines: 2 });
    const response = await page.request.post('/__fixtures/kas-lain', { data: { company: invoiced.company } });

    if (!response.ok()) {
        throw new Error(`The other-cash fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { ...invoiced, ...((await response.json()) as Pick<FinanceRun, 'purchase' | 'purchase_number' | 'transfer_proof' | 'bill'>) };
}

/**
 * CJ-06's warehouse world: TASK-048's company, its confirmed warehouse project of ten at Rp250,000 with three units shipped from the
 * run's lot and received by the client, a condition change to reverse, a return proof (ClientReturnFixture); the invoice layouts'
 * numbering, a bank account and a payment proof of "Bank Uji {code}" (JourneyStockFixture `siplah`); and the purchase of the run's lot
 * with one unit's net cost for the supplier return (ReturnFixture).
 */
export type CorrectionRun = ClientReturnRun & { purchase_of_lot: string; unit_cost: string };

export async function correctionRun(page: Page, run: string): Promise<CorrectionRun> {
    const returned = await clientReturnRun(page, run);
    const finance = await page.request.post('/__fixtures/journey-c/siplah', { data: { company: returned.company } });

    if (!finance.ok()) {
        throw new Error(`The invoice layouts' fixture failed: ${String(finance.status())} ${await finance.text()}`);
    }

    const lot = await page.request.post('/__fixtures/returns', { data: { company: returned.company, lot: returned.lot } });

    if (!lot.ok()) {
        throw new Error(`The return fixture failed: ${String(lot.status())} ${await lot.text()}`);
    }

    const answer = (await lot.json()) as { purchase: string; unit_cost: string };

    return { ...returned, purchase_of_lot: answer.purchase, unit_cost: answer.unit_cost };
}

/** A day as the server's business date counts it (WIB, UTC+7), `YYYY-MM-DD`, `months` before the current month on its `day`. */
function wibDay(months: number, day: number): string {
    const now = new Date(Date.now() + 7 * 3_600_000);
    const month = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() - months, day));

    return month.toISOString().slice(0, 10);
}

/** Today in WIB, `YYYY-MM-DD`. */
export function wibToday(): string {
    return new Date(Date.now() + 7 * 3_600_000).toISOString().slice(0, 10);
}

/**
 * UXS-29's world (JourneyFinanceFixture `period`): the Owner signed in, a company whose go-live is the first day of the previous month
 * — armed for this browser context —, its PROJECT numbering live, its Invoice (DOC-06) numbering monthly — `UJI/INV/{tahun}/{bulan}/
 * {nomor:4}` — started on the previous month's 15th, so that month is not live and has no counter (NM-03), and a confirmed service
 * project of ten at Rp250,000. `period` is that month's key, `day` its 20th, the date the Admin backdates the issue to.
 */
export type PeriodRun = { company: string; code: string; project: string; number: string; goLive: string; period: string; day: string };

export async function periodRun(page: Page, run: string): Promise<PeriodRun> {
    await ownerSignedIn(page, run);
    const goLive = wibDay(1, 1);
    const company = await numberingCompany(page, goLive);
    const response = await page.request.post('/__fixtures/journey-b/periode', { data: { company, go_live: goLive, boundary: wibDay(1, 15) } });

    if (!response.ok()) {
        throw new Error(`The period fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    const answer = (await response.json()) as { code: string; project: string; number: string };

    return { company, ...answer, goLive, period: goLive.slice(0, 7), day: wibDay(1, 20) };
}

/** The signed-in plain account made a one-company Admin of the run's company with `projects.view`, `finance.view` and `invoice.issue` (JourneyFinanceFixture `admin`). */
export async function invoiceAdmin(page: Page, company: string): Promise<void> {
    const response = await page.request.post('/__fixtures/journey-b/admin', { data: { company } });

    if (!response.ok()) {
        throw new Error(`The admin fixture failed: ${String(response.status())} ${await response.text()}`);
    }
}

/** One labelled figure of a definition list — PG-043's *Posisi invoice*, a payment's *Posisi pembayaran* —: the row whose term is exactly the label, its value. */
export function figure(list: Locator, label: string): Locator {
    return list.locator(':scope > div').filter({ has: list.page().locator('dt', { hasText: new RegExp(`^${label}$`) }) }).locator('dd');
}

/** PG-043's position block. */
export function position(page: Page): Locator {
    return page.getByTestId('position');
}

/** The payment sheet's *Posisi pembayaran* on PG-045. */
export function paymentPosition(page: Page): Locator {
    return page.getByTestId('payment').getByRole('region', { name: 'Posisi pembayaran' }).locator('dl');
}

/** PG-043 opened: its number read from the heading `Invoice {nomor}`, its identifier from the address. */
export async function openedInvoice(page: Page): Promise<Invoice> {
    await page.waitForURL(INVOICE_URL);
    const heading = page.getByRole('heading', { level: 1 });
    await expect(heading).toHaveText(/^Invoice UJI\/INV\/\d{4}\/(\d{2}\/)?\d{4}$/);

    return { id: new URL(page.url()).pathname.split('/').pop() ?? '', number: (await heading.innerText()).replace(/^Invoice /, '') };
}

/**
 * J-FIN-01 on PG-042: a value-only DP or termin of `value` beside its live paper, saved, issued after its confirmation — the answer saying
 * that no receivable is active and no cash is recorded —, then opened on PG-043 *Belum Ditagihkan* (BR-FIN-02).
 */
export async function issueValueInvoice(page: Page, project: { project: string; number: string }, description: string, value: number): Promise<Invoice> {
    const content = page.locator('#konten');
    await page.goto(`/keuangan/invoice/baru?proyek=${project.project}`);
    await expect(page.getByRole('heading', { name: new RegExp(`Invoice baru — ${project.number}`) })).toBeVisible();
    await page.getByLabel(/^Keterangan/).fill(description);
    await page.getByRole('textbox', { name: /^Nilai/ }).fill(String(value));
    const paper = page.getByRole('region', { name: /^Pratinjau Invoice/ });
    await expect(paper).toContainText('DRAF — belum terbit');
    await expect(paper).toContainText(description);
    await expect(paper).toContainText(rupiah(value));
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(content.getByText('Draf invoice tersimpan.')).toBeVisible();
    await page.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('belum ada piutang aktif dan belum ada uang masuk');
    await dialog.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await expect(content.getByText(/Invoice UJI\/INV\/\d{4}\/\d{4} sudah terbit\. Belum ada piutang aktif dan belum ada uang yang tercatat\./)).toBeVisible();
    await page.getByRole('link', { name: 'Buka Invoice' }).click();
    const invoice = await openedInvoice(page);
    await expect(figure(position(page), 'Nilai penjualan')).toHaveText(rupiah(value));
    await expect(figure(position(page), 'Pembayaran diterapkan')).toHaveText('Rp0');
    await expect(content).toContainText('Belum Ditagihkan');
    await expect(page.getByTestId('billing')).toContainText('Belum ditagihkan: belum ada piutang aktif.');

    return invoice;
}

/** J-FIN-02 on PG-043: "Tagihkan" with its due date, the answer naming the unpaid remainder that becomes the active receivable. */
export async function bill(page: Page, remainder: number): Promise<void> {
    await page.getByRole('button', { name: 'Tagihkan' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText(`Sisa tagihan ${rupiah(remainder)} menjadi piutang aktif`);
    await dialog.getByRole('textbox', { name: /^Tanggal jatuh tempo/ }).fill('31/12/2099');
    await dialog.getByRole('button', { name: 'Tagihkan' }).click();
    await expect(page.locator('#konten').getByText('Invoice ditagihkan. Sisa tagihannya kini piutang aktif.')).toBeVisible();
    await expect(page.getByTestId('billing')).toContainText('31 Desember 2099');
    await expect(figure(position(page), 'Sisa tagihan')).toHaveText(rupiah(remainder));
}

/** PG-043's "Tindakan lain" → "Catat Pembayaran": PG-046 for the invoice, its company and client set. */
export async function payFromInvoice(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Catat Pembayaran' }).click();
    await page.waitForURL(/\/keuangan\/pembayaran\/baru\?invoice=/);
}

/**
 * PG-046's money received: its amount, the company's account, the proof issued by "Bank Uji {code}", and in the same form the amount
 * applied to each invoice named — the rest staying *Kredit Pelanggan* (SF-APPLY).
 */
export async function fillPayment(page: Page, code: string, amount: number, applications: { invoice: Invoice; amount: number }[]): Promise<void> {
    await page.getByLabel(/^Jumlah/).fill(String(amount));
    await page.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    const proof = page.getByRole('combobox', { name: /^Bukti/ });
    const chosen = await proof.locator('option').filter({ hasText: `Bank Uji ${code}` }).getAttribute('value');
    expect(chosen, `the proof of Bank Uji ${code} is offered`).not.toBeNull();
    await proof.selectOption(chosen ?? '');

    for (const application of applications) {
        await page.getByTestId('application').filter({ hasText: application.invoice.number }).getByLabel(/^Diterapkan/).fill(String(application.amount));
    }
}

/** "Catat Pembayaran" and its confirmation (MSG-39), once a previous confirmation has left the page. */
export async function confirmPayment(page: Page): Promise<void> {
    await expect(page.getByRole('alertdialog')).toHaveCount(0);
    await page.getByRole('button', { name: 'Catat Pembayaran' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Kas masuk akan tercatat dan diterapkan ke invoice yang dipilih; sisanya menjadi Kredit Pelanggan.');
    await dialog.getByRole('button', { name: 'Catat Pembayaran' }).click();
}

/** The payment recorded: PG-045 answers with it first, its sheet open (S2-J06). */
export async function recorded(page: Page, amount: number): Promise<void> {
    await page.waitForURL(/\/keuangan\/pembayaran\?.*tercatat=/);
    await expect(page.locator('#konten').getByText(`Pembayaran ${rupiah(amount)} tercatat.`)).toBeVisible();
    await expect(figure(paymentPosition(page), 'Jumlah')).toHaveText(rupiah(amount));
}
