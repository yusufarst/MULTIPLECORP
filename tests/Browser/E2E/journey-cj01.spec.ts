import { expect as base, type Locator, type Page, type Request, test } from '@playwright/test';

import { journeyRun, type JourneyRun, journeyTrace } from '../journey-a-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { projectRun } from '../project-fixtures';
import { chooseSupplier } from '../purchase-fixtures';
import { quotationRun } from '../quotation-fixtures';
import { currentLine, fillLine, record as recordReceipt } from '../receiving-fixtures';

// TL-09 (TASK-062; CJ-01, the golden business flow; AC-04; AC-20; BR-XD-02; BR-XD-03; GAP-021; criteria 1, 7, 8): the Owner signed in,
// through the real pages — PG-010 → PG-013 → PG-011 with the client chosen and the line added (J-PRJ-01), the quotation drafted, issued
// and approved on PG-014, the approval being the confirmation (J-QUO-01; J-PRJ-02), each line planned on PG-011 (J-FUL-01), its shortage
// bought from PG-011's *Pengadaan* (J-PUR-01), then received into the warehouse with its reservation in the same command (J-INV-01;
// J-INV-02), dispatched (J-INV-03) and delivered on the phone (J-FUL-02) — or shipped direct to the client with its delivery in one
// command (J-FUL-03) —, the invoice generated from the confirmed lines (J-FIN-01), billed (J-FIN-02), paid 300,000 on the phone and
// 700,000 at the desk from PG-011's own step (J-FIN-03), and completed on PG-012 (J-PRJ-03); a third project, created on the phone and
// amended at the desk, served from stock reserved on PG-011 with no purchase and no receipt. Its figures are FX-01 entry 1's exactly; what
// each path created is traced, no purchase, receipt or warehouse movement where it needs none. UXS-41: a quotation of many lines priced
// and issued by keyboard; UXS-44: PG-011's deferred groups within the performance rules. Every test watches its console and network from
// its first step and ends with ReconcileGuards reporting no discrepancy for its company (TS-11; TEST_STRATEGY §8, §16).

// A journey crosses some forty pages and commands from the sign-in to the completion, each test holding one whole flow.
test.describe.configure({ timeout: 300_000 });

// The project, quotation and reservation steps wait as projects.spec, quotations.spec and reservations.spec do; purchasing to
// completion as purchases.spec, receiving.spec, dispatch.spec, deliveries.spec, dropship.spec, invoices.spec, payments.spec and
// completion.spec do.
const expect = base;
const slow = base.configure({ timeout: 60_000 });

const DESK = { width: 1280, height: 900 };
const PHONE = { width: 390, height: 844 };
const PROJECT_URL = /\/proyek\/[0-9a-f-]{36}$/;
const JOURNEY = /^\/proyek\/[0-9a-f-]{36}$/;
const GROUPS = ['documents', 'history', 'predicates'];

// FX-01 entry 1, "AC-10 baseline" (tests/Fixtures/FX-01.php): invoice 1,000,000; outstanding when billed 1,000,000; payments 300,000 then
// 700,000; outstanding 700,000 then 0. Each project sells 4 units at Rp250,000, so its confirmed value is that invoice.
const FX01 = { invoice: 1_000_000, outstandingBilled: 1_000_000, payment1: 300_000, payment2: 700_000, afterPayment1: 700_000, afterPayment2: 0 };
const QUANTITY = '4';
const PRICE = '250000';

type Flow = { run: JourneyRun; title: string; project: string; number: string };

/** The pages' Rupiah (lib/format's money): "Rp", the thousands with dots, no sen. */
const rupiah = (value: number): string => `Rp${String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;

const confirmedLine = (page: Page): Locator => page.getByRole('list', { name: 'Baris terkonfirmasi' }).locator('[data-line="1"]');

/** A figure of the first confirmed line on PG-011, in its order: the demand, then its mode's progress (fulfilment.tsx). */
const fact = (page: Page, index: number): Locator => confirmedLine(page).locator('dd').nth(index);

/** A figure of PG-043's *Posisi invoice* by its label, as the page shows it. */
const position = (page: Page, label: string): Locator => page.getByTestId('position').locator('div').filter({ has: page.locator('dt', { hasText: label }) }).locator('dd');

/**
 * PG-011 reached and settled: its three deferred groups — the predicates, the documents and the history — landed, so the next step and
 * the blockers shown are the full journey's (QB-02), never the root's. The headers of the visit's deferred requests are returned (UXS-44).
 */
async function onJourney(page: Page, go: () => Promise<unknown>): Promise<Record<string, string>[]> {
    const asked: Promise<Record<string, string>>[] = [];
    const listen = (request: Request): void => {
        if (JOURNEY.test(new URL(request.url()).pathname)) {
            asked.push(request.allHeaders());
        }
    };

    page.on('request', listen);
    const landed = Promise.all(GROUPS.map((group) => page.waitForResponse(async (response) => JOURNEY.test(new URL(response.url()).pathname) && (await response.request().headerValue('x-inertia-partial-data')) === group)));
    await go();
    await landed;
    await slow(page.locator('[data-region-state="loading"]')).toHaveCount(0);
    page.off('request', listen);

    return (await Promise.all(asked)).filter((headers) => 'x-inertia-partial-data' in headers);
}

/** Back to PG-011 by the project's link on the page shown (PG-016's facts, PG-043's header). */
async function toProject(page: Page, flow: Flow): Promise<void> {
    await onJourney(page, async () => {
        await page.locator('#konten').locator(`a[href="/proyek/${flow.project}"]:visible`).first().click();
        await page.waitForURL(PROJECT_URL);
    });
}

/** PG-013's parties: the company chosen, the client looked up and chosen with its unit and PIC (J-MD-02's selection), the title. */
async function fillParties(page: Page, run: JourneyRun, title: string): Promise<void> {
    await page.getByRole('combobox', { name: /^Perusahaan/ }).selectOption(run.company);
    await page.getByRole('combobox', { name: 'Klien' }).fill(run.client);
    await page.getByRole('option', { name: run.client }).click();
    await page.getByLabel(/^Unit/).selectOption({ label: run.unit });
    await page.getByLabel(/^PIC/).selectOption({ index: 1 });
    await page.getByLabel(/^Judul proyek/).fill(title);
}

/** The line editor's trailing row: the product looked up and chosen, its default price arriving with it, then its quantity. */
async function addLine(page: Page, product: string, quantity: string): Promise<void> {
    await page.getByRole('combobox', { name: 'Produk/Jasa' }).last().fill(product);
    await page.getByRole('option', { name: product }).click();
    await expect(page.getByRole('combobox', { name: 'Satuan' }).first()).toBeEnabled();
    await page.getByRole('textbox', { name: 'Jumlah' }).first().fill(quantity);
}

async function landedFlow(page: Page, run: JourneyRun, title: string): Promise<Flow> {
    const content = page.locator('#konten');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
    await expect(content.getByText(/UJI\/PRJ\/\d{4}\/0001/).first()).toBeVisible();
    // The record's company label (PT-04; CS-08).
    await expect(content).toContainText(run.code);
    const number = /UJI\/PRJ\/\d{4}\/0001/.exec(await content.innerText())?.[0] ?? '';

    return { run, title, project: new URL(page.url()).pathname.split('/')[2] ?? '', number };
}

/** J-PRJ-01 at the desk: "Buat Proyek" from PG-010, one line of 4 at Rp250,000 by the mode given, landing on PG-011 with its number. */
async function createProject(page: Page, run: JourneyRun, title: string, mode: 'WAREHOUSE' | 'DROPSHIP'): Promise<Flow> {
    await page.goto('/proyek');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Proyek');
    await page.getByRole('link', { name: 'Buat Proyek' }).first().click();
    await page.waitForURL('**/proyek/baru');
    await fillParties(page, run, title);
    await addLine(page, run.product, QUANTITY);

    if (mode === 'DROPSHIP') {
        await page.getByRole('combobox', { name: 'Cara pemenuhan' }).first().selectOption('DROPSHIP');
    }

    await expect(page.locator('#konten')).toContainText(rupiah(FX01.invoice));
    await onJourney(page, async () => {
        await page.getByRole('button', { name: 'Buat Proyek' }).click();
        await page.waitForURL(PROJECT_URL);
    });
    await expect(page.getByText('Proyek dibuat dan nomornya ditetapkan.')).toBeVisible();
    const flow = await landedFlow(page, run, title);
    await expect(page.getByRole('list', { name: 'Tahap proyek' })).toContainText('Kesepakatan');
    await expect(page.locator('#konten')).toContainText(run.product);

    return flow;
}

/**
 * J-PRJ-01 `[both]`: created on the phone — the header, its track and its next step first (NAV-011) —, then amended at the desk through
 * "Ubah proyek", the line added before any confirmation.
 */
async function createOnPhoneAmendAtDesk(page: Page, run: JourneyRun, title: string): Promise<Flow> {
    await page.setViewportSize(PHONE);
    await page.goto('/proyek/baru');
    await fillParties(page, run, title);
    await onJourney(page, async () => {
        await page.getByRole('button', { name: 'Buat Proyek' }).click();
        await page.waitForURL(PROJECT_URL);
    });
    await expect(page.getByText('Proyek dibuat dan nomornya ditetapkan.')).toBeVisible();
    const flow = await landedFlow(page, run, title);
    await expect(page.getByRole('list', { name: 'Tahap proyek' })).toBeVisible();
    await expect(page.locator('#konten')).toContainText('Proyek ini belum memiliki baris.');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

    await page.setViewportSize(DESK);
    await page.getByRole('button', { name: 'Tindakan Lain' }).click();
    await page.getByRole('menuitem', { name: 'Ubah proyek' }).click();
    await page.waitForURL('**/ubah');
    await addLine(page, run.product, QUANTITY);
    await expect(page.locator('#konten')).toContainText(rupiah(FX01.invoice));
    await onJourney(page, async () => {
        await page.getByRole('button', { name: 'Simpan Perubahan' }).click();
        await page.waitForURL(PROJECT_URL);
    });
    await expect(page.getByText('Perubahan proyek disimpan.')).toBeVisible();
    await expect(page.locator('#konten')).toContainText(run.product);

    return flow;
}

/**
 * J-QUO-01 and J-PRJ-02's path A from PG-011's next step: the draft from the project's line on PG-014, its validity and price beside the
 * live paper, "Terbitkan Penawaran" after its confirmation, then "Catat Jawaban Klien" recording the approval as the confirmation, back on
 * PG-011 with the line confirmed in its mode (J-FUL-01) and the confirmed value.
 */
async function quoteAndApprove(page: Page, flow: Flow, mode: string): Promise<void> {
    const content = page.locator('#konten');
    await page.getByRole('link', { name: 'Buat dan terbitkan penawaran, atau konfirmasi dengan bukti pesanan klien' }).click();
    await page.waitForURL(`**/proyek/${flow.project}/penawaran`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${flow.number}`);
    await page.getByRole('button', { name: 'Buat Draf dari Baris Proyek' }).click();
    await expect(page.getByText('Draf penawaran dibuat dari baris proyek.')).toBeVisible();

    await page.getByLabel(/^Berlaku sampai/).fill(`31/12/${String(new Date().getFullYear() + 1)}`);
    await page.getByRole('textbox', { name: /^Harga satuan/ }).first().fill(PRICE);
    const paper = page.getByRole('region', { name: /Pratinjau/ });
    await expect(paper).toContainText('Penawaran Harga');
    await expect(paper).toContainText(rupiah(FX01.invoice));
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(page.getByText('Draf penawaran tersimpan.')).toBeVisible();

    await page.getByRole('button', { name: 'Terbitkan Penawaran' }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText('Penawaran tidak memesan stok dan tidak mencatat uang.');
    await confirm.getByRole('button', { name: 'Terbitkan Penawaran' }).click();
    await expect(content.getByText('Penawaran terbit. Berkasnya sedang disiapkan.')).toBeVisible();
    await expect(content).toContainText(/UJI\/PNW\/\d{4}\/0001/);

    await page.getByRole('button', { name: 'Catat Jawaban Klien' }).first().click();
    await onJourney(page, async () => {
        await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Disetujui' }).click();
        await page.waitForURL(`**/proyek/${flow.project}`);
    });
    await expect(page.getByText('Persetujuan klien dicatat sebagai konfirmasi komersial.')).toBeVisible();
    await expect(content).toContainText('Dikonfirmasi lewat persetujuan penawaran');
    await expect(confirmedLine(page)).toContainText(mode);
    await expect(content).toContainText(rupiah(FX01.invoice));
}

/** J-PUR-01 from PG-011's *Pengadaan*: the shortage prefilled, the project's company shown, the supplier chosen and priced; on PG-016 no cash and no stock yet. */
async function buyShortage(page: Page, flow: Flow, direct: boolean): Promise<string> {
    const content = page.locator('#konten');
    await page.locator('[data-slot="procurement"]').getByRole('link', { name: 'Buat Pembelian' }).click();
    await page.waitForURL(new RegExp(`/pembelian/baru\\?proyek=${flow.project}$`));
    await slow(content).toContainText(flow.number);
    await slow(content).toContainText(flow.run.code);
    const row = page.locator('[data-slot="line-editor"] [data-line]').first();
    await slow(row.getByRole('textbox', { name: 'Jumlah' })).toHaveValue(/^4/);
    await chooseSupplier(page, flow.run.supplier);
    await row.getByRole('textbox', { name: 'Harga' }).fill('200000');
    await page.getByRole('button', { name: 'Simpan Pembelian' }).click();

    await page.waitForURL(/\/pembelian\/[0-9a-f-]{36}$/);
    await slow(page.getByText('Belum ada uang keluar dan belum ada barang masuk.')).toBeVisible();
    await slow(content).toContainText('UJI/BELI/');
    await slow(content).toContainText(flow.run.supplier);
    await slow(content).toContainText('Belum ada penerimaan.');

    if (direct) {
        await slow(content).toContainText('Kirim langsung ke klien');
    }

    return new URL(page.url()).pathname.split('/')[2] ?? '';
}

/** J-INV-01 from PG-016's "Terima Barang": the 4 usable to the rack and "Reservasi untuk Proyek Ini" in the same command (D-UX-12; J-INV-02). */
async function receive(page: Page, flow: Flow, purchase: string): Promise<void> {
    await page.getByRole('link', { name: 'Terima Barang' }).click();
    await page.waitForURL(`**/gudang/barang-masuk/${purchase}`);
    await slow(page.getByRole('heading', { name: 'Terima Barang', exact: true })).toBeVisible();
    await slow(page.locator('#konten')).toContainText(flow.number);
    await slow(currentLine(page)).toContainText(/Maksimal \d+ unit/);
    await fillLine(page, QUANTITY, flow.run.rack);
    await currentLine(page).getByRole('textbox', { name: 'Reservasi untuk Proyek Ini' }).fill(QUANTITY);
    await recordReceipt(page);
    await slow(page.locator('[data-slot="receive-progress"]')).toHaveText('1 baris tercatat · 0 baris tersisa');
    await slow(page.locator('[data-slot="receive-summary"]').getByRole('listitem')).toHaveCount(1);
}

/** J-INV-02 on PG-011: "Reservasi" on the confirmed warehouse line, at most its demand against the stock available (AX-01). */
async function reserveOnJourney(page: Page): Promise<void> {
    const line = confirmedLine(page);
    await expect(line).toContainText('Tersedia di gudang');
    await line.getByRole('button', { name: 'Reservasi' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText(`Paling banyak ${QUANTITY}`);
    await expect(dialog).toContainText('Tidak ada barang yang bergerak.');
    await dialog.getByRole('textbox').fill(QUANTITY);
    await dialog.getByRole('button', { name: 'Reservasi' }).click();
    await expect(page.locator('#konten').getByText('Reservasi tersimpan.')).toBeVisible();
    await expect(fact(page, 1)).toHaveText(/^4 /);
}

/** J-INV-03 from PG-023's worklist: the project's reserved line, its lot tapped, "Catat Barang Keluar" after its confirmation. */
async function dispatch(page: Page, flow: Flow): Promise<void> {
    await page.goto(`/gudang/barang-keluar?perusahaan=${flow.run.company}`);
    await slow(page.getByRole('heading', { name: 'Barang Keluar', exact: true })).toBeVisible();
    await slow(page.locator('#konten')).toContainText(flow.run.product);
    await page.locator('#konten').locator(`a[href="/gudang/barang-keluar/${flow.project}"]:visible`).first().click();
    await page.waitForURL(`**/gudang/barang-keluar/${flow.project}`);
    await slow(page.getByRole('heading', { name: 'Keluarkan Barang', exact: true })).toBeVisible();

    const line = page.locator('[data-slot="dispatch-line"]:visible');
    await line.getByLabel('Jumlah keluar').fill(QUANTITY);
    await line.locator('[data-slot="dispatch-lot"]').first().getByRole('button').first().click();
    await line.getByRole('button', { name: 'Catat Barang Keluar' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Barang Keluar' }).click();
    await slow(line).toContainText('Barang keluar tercatat');
    await slow(page.locator('[data-slot="dispatch-record"]')).toHaveCount(1);
}

/** J-FUL-02 `[both]` on the phone from PG-030's awaiting shipments: what reached the client with its proof, stock untouched (BR-INV-02). */
async function deliverOnPhone(page: Page, flow: Flow): Promise<void> {
    await page.setViewportSize(PHONE);
    await page.goto('/gudang/pengiriman');
    await page.locator('[data-slot="delivery-row"]').filter({ hasText: flow.run.code }).first().getByRole('link').first().click();
    await page.waitForURL(/\/gudang\/pengiriman\/[0-9a-f-]{36}$/);
    const shipment = page.locator('[data-slot="delivery-shipment"]');
    await slow(shipment).toContainText(flow.number);
    await slow(shipment).toContainText('Stok tidak berubah di sini.');

    const form = page.locator('[data-slot="delivery-form"]');
    await page.locator('[data-slot="delivery-line"]').first().getByLabel(/^Jumlah/).fill(QUANTITY);
    await form.getByLabel('Nama penerima').fill('Bu Sari');
    await form.getByLabel('Bukti pengiriman').selectOption({ value: flow.run.delivery_proof });
    await form.getByRole('button', { name: 'Catat Pengiriman', exact: true }).click();
    await page.waitForURL(/\/gudang\/pengiriman$/);
    await slow(page.locator('#konten')).toContainText('Pengiriman tercatat.');
    await page.setViewportSize(DESK);
}

/** J-FUL-03 from PG-011's next step: the supplier's shipment confirmed with its reference and proof, the client's delivery in the same command (AX-08; L-40). */
async function shipDirect(page: Page, flow: Flow): Promise<void> {
    await page.getByRole('link', { name: 'Pesan dan konfirmasi kirim langsung' }).click();
    await page.waitForURL(new RegExp(`/proyek/${flow.project}/kirim-langsung$`));
    await slow(page.locator('[data-slot="dropship-line"]').first()).toContainText(QUANTITY);
    const form = page.locator('[data-slot="dropship-form"]');
    await form.getByLabel('Nomor pengiriman pemasok').fill(`SJ-PMS-${flow.run.code}`);
    await page.locator('[data-slot="dropship-entry"]').first().getByLabel(/^Jumlah dikirim/).fill(QUANTITY);
    await form.getByLabel('Bukti dari pemasok').selectOption({ value: flow.run.supplier_proof });
    await form.getByRole('checkbox', { name: /Bukti terima klien sudah ada/ }).click();
    await form.getByLabel('Nama penerima').fill('Bu Sari');
    await form.getByLabel('Bukti terima klien', { exact: true }).selectOption({ value: flow.run.client_proof });
    await form.getByRole('button', { name: 'Konfirmasi Kirim Langsung', exact: true }).click();
    await onJourney(page, async () => {
        await page.getByRole('alertdialog').getByRole('button', { name: 'Konfirmasi Kirim Langsung', exact: true }).click();
        await page.waitForURL(PROJECT_URL);
    });
    await slow(page.locator('#konten')).toContainText('Kirim langsung terkonfirmasi.');
    // *Kirim langsung*'s figures: confirmed by the supplier, then received by the client.
    await slow(fact(page, 1)).toHaveText(/^4 /);
    await slow(fact(page, 2)).toHaveText(/^4 /);
}

/**
 * J-FIN-01: the invoice generated from the confirmed line on PG-042 beside its live paper, saved, issued after its confirmation —
 * nothing yet billed —, then opened on PG-043. PG-011 names this address as its invoice step (ProjectWorkspace::invoiceHref), but once its
 * predicates land the step shown is the default invoice requirement, which carries no link: the composer is opened at that address.
 */
async function issueInvoice(page: Page, flow: Flow): Promise<string> {
    await page.goto(`/keuangan/invoice/baru?proyek=${flow.project}`);
    await slow(page.getByRole('heading', { name: new RegExp(`Invoice baru — ${flow.number}`) })).toBeVisible();
    await page.getByRole('radio', { name: 'Dari baris proyek' }).click();
    await page.getByTestId('invoice-line').getByRole('textbox').fill(QUANTITY);
    const paper = page.getByRole('region', { name: /^Pratinjau Invoice/ });
    await slow(paper).toContainText('DRAF — belum terbit');
    await slow(paper).toContainText(rupiah(FX01.invoice));
    await page.getByRole('button', { name: 'Simpan Draf' }).click();
    await slow(page.locator('#konten').getByText('Draf invoice tersimpan.')).toBeVisible();

    await page.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    const dialog = page.getByRole('alertdialog');
    await slow(dialog).toContainText('belum ada piutang aktif');
    await dialog.getByRole('button', { name: 'Terbitkan Invoice' }).click();
    await slow(page.locator('#konten').getByText(/Invoice UJI\/INV\/.* sudah terbit/)).toBeVisible();
    await page.getByRole('link', { name: 'Buka Invoice' }).click();
    await page.waitForURL(/\/keuangan\/invoice\/[0-9a-f-]{36}$/);
    await slow(position(page, 'Nilai penjualan')).toHaveText(rupiah(FX01.invoice));
    await slow(page.locator('#konten')).toContainText('Belum Ditagihkan');
    await slow(page.getByTestId('billing')).toContainText('Belum ditagihkan');

    return page.url();
}

/** J-FIN-02 on PG-043: "Tagihkan" with its due date, the outstanding now an active receivable of FX-01's 1,000,000. */
async function bill(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Tagihkan' }).click();
    const dialog = page.getByRole('alertdialog');
    await slow(dialog).toContainText(`${rupiah(FX01.outstandingBilled)} menjadi piutang aktif`);
    await dialog.getByRole('textbox', { name: /^Tanggal jatuh tempo/ }).fill('31/12/2099');
    await dialog.getByRole('button', { name: 'Tagihkan' }).click();
    await slow(page.locator('#konten').getByText('Invoice ditagihkan. Sisa tagihannya kini piutang aktif.')).toBeVisible();
    await slow(page.getByTestId('billing')).toContainText('31 Desember 2099');
    await slow(position(page, 'Sisa tagihan')).toHaveText(rupiah(FX01.outstandingBilled));
}

/** PG-046's payment: its amount, the company's account, its proof chosen by its issuer, all of it applied to the invoice, after MSG-39. */
async function pay(page: Page, amount: number, issuer: string): Promise<void> {
    await page.getByLabel(/^Jumlah/).fill(String(amount));
    await page.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    const proof = page.getByRole('combobox', { name: /^Bukti/ });
    const chosen = await proof.locator('option').filter({ hasText: issuer }).getAttribute('value');
    expect(chosen, `the proof of ${issuer} is offered`).not.toBeNull();
    await proof.selectOption(chosen ?? '');
    await page.getByTestId('application').getByLabel(/^Diterapkan/).fill(String(amount));
    await slow(page.getByRole('alertdialog')).toHaveCount(0);
    await page.getByRole('button', { name: 'Catat Pembayaran' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Pembayaran' }).click();
    await page.waitForURL(/\/keuangan\/pembayaran\?.*tercatat=/);
    await slow(page.getByTestId('payment')).toContainText(rupiah(amount));
}

/**
 * J-FIN-03 `[both]`: 300,000 on the phone from PG-043's own action, then 700,000 at the desk from PG-011's next step (NAV-046), each
 * applied in the same command; the invoice's outstanding 700,000, then 0 (FX-01 1).
 */
async function payments(page: Page, flow: Flow, invoice: string): Promise<void> {
    await page.getByRole('button', { name: 'Tindakan lain' }).click();
    await page.getByRole('menuitem', { name: 'Catat Pembayaran' }).click();
    await page.waitForURL(/\/keuangan\/pembayaran\/baru\?invoice=/);
    await page.setViewportSize(PHONE);
    await pay(page, FX01.payment1, flow.run.payment_proofs[0]);
    await page.setViewportSize(DESK);
    await page.goto(invoice);
    await slow(position(page, 'Pembayaran diterapkan')).toHaveText(rupiah(FX01.payment1));
    await slow(position(page, 'Sisa tagihan')).toHaveText(rupiah(FX01.afterPayment1));

    await toProject(page, flow);
    await page.getByRole('link', { name: '1 invoice masih punya sisa tagihan' }).click();
    await page.waitForURL(new RegExp(`/keuangan/pembayaran/baru\\?proyek=${flow.project}$`));
    await slow(page.getByTestId('application')).toHaveCount(1);
    await pay(page, FX01.payment2, flow.run.payment_proofs[1]);
    await page.goto(invoice);
    await slow(position(page, 'Pembayaran diterapkan')).toHaveText(rupiah(FX01.payment1 + FX01.payment2));
    await slow(position(page, 'Sisa tagihan')).toHaveText(rupiah(FX01.afterPayment2));
}

/** J-PRJ-03 from PG-011's next step: PG-012 with its ten conditions met, "Selesaikan Proyek" after its confirmation (AX-25). */
async function complete(page: Page, flow: Flow): Promise<void> {
    await page.getByRole('link', { name: 'Selesaikan Proyek', exact: true }).click();
    await page.waitForURL(`**/proyek/${flow.project}/penyelesaian`);
    await slow(page.getByRole('heading', { name: 'Penyelesaian Proyek', level: 1 })).toBeVisible();
    await slow(page.locator('[data-slot="completion-condition"]')).toHaveCount(10);
    await slow(page.locator('[data-slot="completion-condition"][data-met="false"]')).toHaveCount(0);
    await page.getByRole('button', { name: 'Selesaikan Proyek', exact: true }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Selesaikan Proyek', exact: true }).click();
    await slow(page.locator('#konten')).toContainText('Proyek diselesaikan.');
}

/** Tab, as a keyboard alone does, until the field holds the focus, within `most` presses: the form's one focus order (UXS-41). */
async function tabTo(page: Page, target: Locator, most: number): Promise<void> {
    for (let press = 0; press < most && !(await target.evaluate((element) => element === document.activeElement)); press++) {
        await page.keyboard.press('Tab');
    }

    await expect(target).toBeFocused();
}

test('CJ-01 received into the warehouse: project, quotation, approval, purchase, receipt with its reservation, dispatch, delivery on the phone, invoice, billing, payments, completion (AC-20; FX-01 1; criterion 1)', async ({ page }, testInfo) => {
    await page.setViewportSize(DESK);
    const captured = watch(page);
    const run = await journeyRun(page, `cj01-gudang-${String(Date.now())}`);

    const flow = await createProject(page, run, `Pengadaan buku gudang ${run.code}`, 'WAREHOUSE');
    await quoteAndApprove(page, flow, 'Dari gudang');
    await expect(confirmedLine(page)).toContainText('Kurang');
    const purchase = await buyShortage(page, flow, false);
    await receive(page, flow, purchase);
    await onJourney(page, () => page.goto(`/proyek/${flow.project}`));
    await slow(fact(page, 1)).toHaveText(/^4 /);
    await dispatch(page, flow);
    await deliverOnPhone(page, flow);
    await onJourney(page, () => page.goto(`/proyek/${flow.project}`));
    // *Gudang*'s figures: dispatched, then received by the client.
    await slow(fact(page, 2)).toHaveText(/^4 /);
    await slow(fact(page, 3)).toHaveText(/^4 /);

    const invoice = await issueInvoice(page, flow);
    await bill(page);
    await payments(page, flow, invoice);
    await toProject(page, flow);
    await complete(page, flow);

    const traced = await journeyTrace(page, run, flow.project);
    expect(traced.project_purchases, 'the shortage bought once').toBe(1);
    expect(traced.project_receipts, 'received once into the warehouse').toBe(1);
    expect(traced.movements, 'the receipt and the dispatch, nothing else moved').toEqual({ DISPATCH: 1, RECEIPT: 1 });

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

test('CJ-01 shipped direct to the client: project, quotation, approval, purchase, direct shipment with its delivery, invoice, billing, payments, completion, with no receipt and no warehouse movement (BR-XD-03; BR-INV-11; FX-01 1; criterion 1)', async ({ page }, testInfo) => {
    await page.setViewportSize(DESK);
    const captured = watch(page);
    const run = await journeyRun(page, `cj01-langsung-${String(Date.now())}`);

    const flow = await createProject(page, run, `Pengadaan buku langsung ${run.code}`, 'DROPSHIP');
    await quoteAndApprove(page, flow, 'Kirim langsung');
    const purchase = await buyShortage(page, flow, true);
    await toProject(page, flow);
    await shipDirect(page, flow);

    const invoice = await issueInvoice(page, flow);
    await bill(page);
    await payments(page, flow, invoice);
    await toProject(page, flow);
    await complete(page, flow);

    await page.goto(`/pembelian/${purchase}`);
    await slow(page.locator('#konten')).toContainText('Belum ada penerimaan.');
    const traced = await journeyTrace(page, run, flow.project);
    expect(traced.project_purchases, 'the direct shipment\'s own purchase').toBe(1);
    expect(traced.project_receipts, 'no fictitious receipt (GAP-021)').toBe(0);
    expect(traced.company_receipts, 'no receipt anywhere in the company').toBe(0);
    expect(traced.movements, 'no warehouse movement for a direct shipment').toEqual({});

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

test('CJ-01 served from stock with no purchase: project created on the phone and amended at the desk, quotation, approval, reservation on PG-011, dispatch, delivery on the phone, invoice, billing, payments, completion (GAP-021; BR-XD-02; FX-01 1; criterion 1)', async ({ page }, testInfo) => {
    await page.setViewportSize(DESK);
    const captured = watch(page);
    const run = await journeyRun(page, `cj01-stok-${String(Date.now())}`, { stock: '10' });
    // The stock on hand before the journey: the replenishment the fixture received, the company's and no project's.
    const stocked = await journeyTrace(page, run, null);

    const flow = await createOnPhoneAmendAtDesk(page, run, `Pengadaan buku stok ${run.code}`);
    await quoteAndApprove(page, flow, 'Dari gudang');
    await reserveOnJourney(page);
    await dispatch(page, flow);
    await deliverOnPhone(page, flow);
    await onJourney(page, () => page.goto(`/proyek/${flow.project}`));
    await slow(fact(page, 2)).toHaveText(/^4 /);
    await slow(fact(page, 3)).toHaveText(/^4 /);

    const invoice = await issueInvoice(page, flow);
    await bill(page);
    await payments(page, flow, invoice);
    await toProject(page, flow);
    await slow(page.locator('[data-slot="procurement"]')).toContainText('Belum ada pembelian untuk proyek ini.');
    await complete(page, flow);

    const traced = await journeyTrace(page, run, flow.project);
    expect(traced.project_purchases, 'no dummy purchase (GAP-021)').toBe(0);
    expect(traced.project_receipts, 'no dummy receipt (GAP-021)').toBe(0);
    expect(traced.company_purchases, 'no purchase anywhere in the company during the journey').toBe(stocked.company_purchases);
    expect(traced.company_receipts, 'no receipt anywhere in the company during the journey').toBe(stocked.company_receipts);
    expect(traced.movements, 'only its dispatch moved the stock').toEqual({ ...stocked.movements, DISPATCH: 1 });

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

test('UXS-41: a quotation of many lines priced and issued by keyboard on the desktop, one focus order through its rows, the focus visible (CJ-01; J-QUO-01; PT-10; KB rows)', async ({ page }, testInfo) => {
    await page.setViewportSize(DESK);
    const captured = watch(page);
    const run = await quotationRun(page, `cj01-uxs41-${String(Date.now())}`, { lines: 12 });

    await page.goto(`/proyek/${run.project}/penawaran`);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Penawaran ${run.number}`);
    await page.getByRole('button', { name: 'Buat Draf dari Baris Proyek' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByText('Draf penawaran dibuat dari baris proyek.')).toBeVisible();

    const prices = page.getByRole('textbox', { name: /^Harga satuan/ });
    await expect(prices).toHaveCount(12);
    await page.getByLabel(/^Berlaku sampai/).focus();
    await page.keyboard.type(`31/12/${String(new Date().getFullYear() + 1)}`);

    for (let index = 0; index < 12; index++) {
        const price = prices.nth(index);
        await tabTo(page, price, 8);
        expect(await price.evaluate((element) => element.matches(':focus-visible')), `line ${String(index + 1)}'s price shows its focus`).toBe(true);
        await page.keyboard.press('ControlOrMeta+A');
        await page.keyboard.type('20000');
    }

    const save = page.getByRole('button', { name: 'Simpan Draf' });
    await tabTo(page, save, 8);
    await page.keyboard.press('Enter');
    await expect(page.getByText('Draf penawaran tersimpan.')).toBeVisible();

    for (let index = 0; index < 12; index++) {
        await expect(prices.nth(index)).toHaveValue('Rp20.000');
    }

    await expect(page.getByRole('region', { name: /Pratinjau/ })).toContainText('Rp20.000');

    await page.getByRole('button', { name: 'Terbitkan Penawaran' }).focus();
    await page.keyboard.press('Enter');
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText('Penawaran tidak memesan stok dan tidak mencatat uang.');
    await tabTo(page, confirm.getByRole('button', { name: 'Terbitkan Penawaran' }), 10);
    await page.keyboard.press('Enter');
    await expect(page.locator('#konten').getByText('Penawaran terbit. Berkasnya sedang disiapkan.')).toBeVisible();
    await expect(page.locator('#konten')).toContainText(/UJI\/PNW\/\d{4}\/0001/);

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});

test('UXS-44: PG-011 within the performance rules, at most three deferred groups, each asked once as a background request (CJ-01; PF-34; QB-02; RV-06)', async ({ page }, testInfo) => {
    await page.setViewportSize(DESK);
    const captured = watch(page);
    const run = await projectRun(page, `cj01-uxs44-${String(Date.now())}`, { project: true });

    const deferred = await onJourney(page, () => page.goto(`/proyek/${run.project ?? ''}`));
    await expect(page.getByRole('list', { name: 'Tahap proyek' })).toBeVisible();
    const groups = deferred.map((headers) => headers['x-inertia-partial-data'] ?? '');
    expect(groups, 'the predicates, the documents and the history each arrive deferred').toEqual(expect.arrayContaining(GROUPS));
    expect(deferred.map((headers) => headers['x-background-request']), 'a deferred group is a background request, never extending the session (RV-06)').toEqual(deferred.map(() => '1'));
    expect(groups.length, 'no group asked twice in one visit: nothing polls it').toBe(new Set(groups).size);
    expect([...groups].sort(), 'PF-34: a page uses at most three deferred groups').toEqual(GROUPS);

    await guardsClean(page, testInfo, [run.company]);
    await clean(captured, testInfo);
});
