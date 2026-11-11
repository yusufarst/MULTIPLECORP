import { expect as base, type Locator, type Page, test } from '@playwright/test';

import { rendition } from '../document-fixtures';
import { invoiceRun } from '../invoice-fixtures';
import { issuedPaper, longDate, retryRendition } from '../journey-b2-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-063; CJ-15, the generated document lifecycle; J-DOC-01; UXS-09; AC-08; DESIGN_SYSTEM §14.3; DIR-081 OB-02; TEST_STRATEGY
// §9; criteria 4, 7, 8): the *Surat Permintaan Pembayaran* (DOC-13) prepared from PG-043's recorded facts — an invoice of
// Rp1,000,000 billed with its due date and Rp400,000 of it paid — and opened on PG-036, its draft paper showing exactly those
// facts, marked "DRAF — belum terbit" with no number. The composer at every width §9 names: the form beside the live preview at
// 1,280 px (the preview at 45 %), 1,279 and 1,025 px (40 %), the tablet's "Rincian | Pratinjau" toggle at 1,024 px, and the phone's
// Edit → Preview → Finalize at 390 px with the preview full-screen — a value entered in the form shown on the paper at once, nothing
// written by previewing. Issued with its confirmation into its number; its rendition failing (MSG-08 with the attempt, *Terbit* and
// the same number, no re-issue), then rendered by the automatic retry into the inline PDF — the number unchanged; the issued paper's
// content asserted against the same facts, and the invoice unchanged by the document. Its console and network captured and
// ReconcileGuards clean at its end.

// One journey crosses PG-043, PG-036 at five widths and back, with two committed commands and two rendition attempts.
test.describe.configure({ timeout: 300_000 });

const DOCUMENT = /\/dokumen\/[0-9a-f-]{36}$/;
const NAME = 'Surat Permintaan Pembayaran';

/** One labelled figure of a definition list: the row whose term is exactly the label, its value. */
function figure(page: Page, list: Locator, label: string): Locator {
    return list.locator(':scope > div').filter({ has: page.locator('dt', { hasText: new RegExp(`^${label}$`) }) }).locator('dd');
}

/** One row of the paper's tables — its references or its totals — by its label: the value beside it. */
function paperFact(page: Page, paper: Locator, label: string): Locator {
    return paper.locator('tr').filter({ has: page.locator('th', { hasText: new RegExp(`^${label}$`) }) }).locator('td');
}

test('CJ-15: a document drafted from the recorded facts in the composer at the desk, the tablet and the phone, issued with its number, its failed rendition keeping *Terbit* and the number (J-DOC-01; UXS-09; AC-08; criterion 4)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await invoiceRun(page, `cj15-${String(Date.now())}`, { stage: 'issued', billed: true, bank: true, paid: '400000', apply: true });
    const content = page.locator('#konten');
    const title = page.getByRole('heading', { level: 1 });
    const position = (label: string) => figure(page, page.getByTestId('position'), label);

    // The recorded facts on PG-043: the invoice's number, its value, what was paid and what remains, the billing's due date.
    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(title).toHaveText(/^Invoice UJI\/INV\/\d{4}\/\d{4}$/);
    const invoiceNumber = ((await title.textContent()) ?? '').replace(/^Invoice /, '');
    await expect(position('Nilai tagihan')).toHaveText('Rp1.000.000');
    await expect(position('Pembayaran diterapkan')).toHaveText('Rp400.000');
    await expect(position('Sisa tagihan')).toHaveText('Rp600.000');
    await expect(page.getByTestId('billing')).toContainText('31 Desember 2099');

    // J-DOC-01: the draft prepared from its business record and opened on PG-036 (DIR-081 OB-02).
    await content.getByRole('button', { name: 'Buat Surat Permintaan Pembayaran' }).click();
    await page.waitForURL(DOCUMENT);
    const document = new URL(page.url()).pathname.split('/').pop() ?? '';
    await expect(title).toHaveText(NAME);
    const composer = page.locator('[data-slot="document-composer"]');
    const paper = page.getByRole('region', { name: `Pratinjau ${NAME}, draf belum terbit` });
    const bank = page.getByLabel('Rekening tujuan pembayaran');
    const today = (await content.locator('[data-slot="business-date"] time').getAttribute('datetime')) ?? '';
    expect(today).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    await expect(content.locator('[data-slot="business-date"]')).toContainText('Hari ini');

    // The draft paper states the record's facts, marked as a draft, with no number (DESIGN_SYSTEM §14.3; DC-2–DC-4).
    await expect(paper).toContainText(NAME);
    await expect(paper).toContainText('DRAF — belum terbit');
    await expect(paper).toContainText('Nomor diberikan saat terbit');
    await expect(paper).toContainText(run.client_name);
    await expect(paper).toContainText(`Pembayaran invoice ${invoiceNumber}`);
    await expect(paperFact(page, paper, 'Tanggal')).toHaveText(longDate(today));
    await expect(paperFact(page, paper, 'No. Invoice')).toHaveText(invoiceNumber);
    await expect(paperFact(page, paper, 'Tanggal Tagihan')).toHaveText(longDate(today));
    await expect(paperFact(page, paper, 'Jatuh Tempo')).toHaveText('31 Desember 2099');
    await expect(paperFact(page, paper, 'Nilai Tagihan')).toHaveText('Rp1.000.000');
    await expect(paperFact(page, paper, 'Sudah Diterima atau Dikurangi')).toHaveText('Rp400.000');
    await expect(paperFact(page, paper, 'Sisa Tagihan')).toHaveText('Rp600.000');
    await expect(paper).toContainText('Sejumlah Rp600.000');
    await expect(paper).toContainText(/enam ratus ribu rupiah/i);
    await expect(paper).not.toContainText('Pembayaran ditransfer ke');

    // Previewing never writes (D-DS-20; GAP-053): no command leaves the page while the draft is composed at any width.
    const writes: string[] = [];
    page.on('request', (request) => {
        const path = new URL(request.url()).pathname;

        if (request.method() !== 'GET' && !path.startsWith('/__fixtures')) writes.push(`${request.method()} ${path}`);
    });

    // The live preview: the bank destination entered in the form is on the paper at once.
    const [bankName = '', accountNumber = '', holder = ''] = ((await bank.locator('option').nth(1).textContent()) ?? '').split(' · ');
    expect(accountNumber).not.toBe('');
    await bank.selectOption({ index: 1 });
    await expect(paper).toContainText('Pembayaran ditransfer ke');
    await expect(paper).toContainText(bankName);
    await expect(paper).toContainText(`No. rekening ${accountNumber} a.n. ${holder}`);

    /** The desktop band: the form on the left, the live preview on the right at `share` of the composer — never a tab (D-DS-23; CD-04). */
    const beside = async (width: number, share: number): Promise<void> => {
        await page.setViewportSize({ width, height: 900 });
        await expect(composer).toHaveAttribute('data-band', 'desktop');
        await expect(page.getByRole('tab', { name: 'Pratinjau' })).toHaveCount(0);
        await expect(bank).toBeVisible();
        await expect(paper).toBeVisible();
        await expect(paper).toContainText(`No. rekening ${accountNumber}`);
        const form = composer.locator(':scope > div').nth(0);
        const preview = composer.locator(':scope > div').nth(1);
        await expect.poll(async () => {
            const [whole, left, right] = await Promise.all([composer.boundingBox(), form.boundingBox(), preview.boundingBox()]);

            return whole !== null && left !== null && right !== null && right.x >= left.x + left.width && Math.abs(right.width - whole.width * share) <= 1.5;
        }, { message: `at ${String(width)} px the preview sits right of the form at ${String(share * 100)} %` }).toBe(true);
    };

    await beside(1280, 0.45);
    await beside(1279, 0.4);
    await beside(1025, 0.4);

    // The tablet's toggle at 1,024 px: "Rincian | Pratinjau", the form first, the same live paper behind the second tab.
    await page.setViewportSize({ width: 1024, height: 900 });
    await expect(composer).toHaveAttribute('data-band', 'tablet');
    await expect(page.getByRole('tab', { name: 'Rincian' })).toHaveAttribute('aria-selected', 'true');
    await expect(bank).toBeVisible();
    await expect(paper).toBeHidden();
    await page.getByRole('tab', { name: 'Pratinjau' }).click();
    await expect(paper).toBeVisible();
    await expect(paper).toContainText(`No. rekening ${accountNumber} a.n. ${holder}`);
    await expect(paperFact(page, paper, 'Sisa Tagihan')).toHaveText('Rp600.000');
    await page.getByRole('tab', { name: 'Rincian' }).click();
    await expect(bank).toBeVisible();
    await expect(bank).not.toHaveValue('');

    // The phone at 390 px: Edit → Preview → Finalize, the preview full-screen (DC-11; INT-012).
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(composer).toHaveAttribute('data-band', 'phone');
    const steps = page.getByRole('list', { name: 'Langkah' }).getByRole('listitem');
    await expect(steps).toHaveText(['1. Ubah', '2. Pratinjau', '3. Selesaikan']);
    await expect(steps.nth(0)).toHaveAttribute('aria-current', 'step');
    await expect(bank).toBeVisible();
    await expect(paper).toBeHidden();
    await content.getByRole('button', { name: 'Pratinjau', exact: true }).click();
    const sheet = page.getByRole('dialog');
    await expect(sheet.getByRole('region', { name: `Pratinjau ${NAME}, draf belum terbit` })).toContainText('DRAF — belum terbit');
    await expect(sheet.getByRole('region', { name: `Pratinjau ${NAME}, draf belum terbit` })).toContainText(`No. rekening ${accountNumber} a.n. ${holder}`);
    await expect.poll(async () => (await sheet.boundingBox())?.width ?? 0, { message: 'the preview fills the phone\'s width' }).toBeGreaterThanOrEqual(389);
    await sheet.getByRole('button', { name: 'Lanjut' }).click();
    await expect(sheet).toBeHidden();
    await expect(steps.nth(2)).toHaveAttribute('aria-current', 'step');
    await expect(content.getByRole('button', { name: 'Simpan Draf' })).toBeVisible();
    await expect(content.getByRole('button', { name: `Terbitkan ${NAME}` })).toBeVisible();
    expect(writes, 'composing and previewing the draft writes nothing (D-DS-20)').toEqual([]);

    // Back at the desk: the draft saved with the bank destination entered, then issued through its confirmation into its number (IP-08; AX-15).
    await page.setViewportSize({ width: 1280, height: 900 });
    await expect(composer).toHaveAttribute('data-band', 'desktop');
    await content.getByRole('button', { name: 'Simpan Draf' }).click();
    await expect(content.getByText('Draf tersimpan.')).toBeVisible();
    await page.reload();
    await expect(bank).not.toHaveValue('');
    await expect(paper).toContainText(`No. rekening ${accountNumber} a.n. ${holder}`);
    await content.getByRole('button', { name: `Terbitkan ${NAME}` }).click();
    const confirm = page.getByRole('alertdialog', { name: `Terbitkan ${NAME}?` });
    await expect(confirm).toContainText('Nomor akan ditetapkan dan tidak dapat diubah.');
    await expect(confirm).toContainText(`Tanggal dokumen: ${longDate(today)}`);
    await confirm.getByRole('button', { name: `Terbitkan ${NAME}` }).click();
    await expect(confirm).toBeHidden();
    await expect(content.getByText('PDF sedang dibuat. Dokumen sudah terbit.')).toBeVisible();
    await expect(title).toHaveText(new RegExp(`^${NAME} UJI/SPP/${today.slice(0, 4)}/\\d{4}$`));
    const number = ((await title.textContent()) ?? '').replace(`${NAME} `, '');
    const version = content.locator('li[data-version="1"]');
    await expect(version).toContainText('Terbit');
    await expect(version).toContainText(number);
    await expect(version).toContainText('Berlaku');
    await expect(content.getByText('PDF Sedang Dibuat', { exact: true })).toBeVisible();
    await expect(paper).toBeHidden();
    await expect(content.getByRole('button', { name: /^Terbitkan/ })).toHaveCount(0);

    // UXS-09: the rendition fails in the background — the document stays *Terbit* with the same number, MSG-08 with the attempt, no re-issue.
    await rendition(page, document, 'FAILED');
    await page.reload();
    await expect(content.getByText('PDF Gagal Dibuat', { exact: true })).toBeVisible();
    await expect(content.getByText(`${NAME} sudah terbit dengan nomor ${number}. PDF gagal dibuat dan akan dicoba lagi otomatis (percobaan 1 dari 5).`, { exact: true })).toBeVisible();
    await expect(title).toHaveText(`${NAME} ${number}`);
    await expect(version).toContainText('Terbit');
    await expect(version).toContainText(number);
    await expect(content.getByRole('button', { name: /^Terbitkan/ })).toHaveCount(0);
    await expect(content.locator('iframe')).toHaveCount(0);

    // The issued paper — what the renderer renders from the version's payload — states the same facts, its number in place of the draft's line.
    const issued = await issuedPaper(page, document);
    expect(issued).toContain(NAME);
    expect(issued).toContain(`Nomor: ${number}`);
    expect(issued).not.toContain('DRAF');
    expect(issued).not.toContain('Nomor diberikan saat terbit');
    expect(issued).toContain(run.client_name);
    expect(issued).toContain(`Pembayaran invoice ${invoiceNumber}`);
    expect(issued).toContain(`Tanggal ${longDate(today)}`);
    expect(issued).toContain(`No. Invoice ${invoiceNumber}`);
    expect(issued).toContain(`Tanggal Tagihan ${longDate(today)}`);
    expect(issued).toContain('Jatuh Tempo 31 Desember 2099');
    expect(issued).toContain('Nilai Tagihan Rp1.000.000');
    expect(issued).toContain('Sudah Diterima atau Dikurangi Rp400.000');
    expect(issued).toContain('Sisa Tagihan Rp600.000');
    expect(issued).toContain('Sejumlah Rp600.000');
    expect(issued).toContain(`No. rekening ${accountNumber} a.n. ${holder}`);

    // Back on its record: the invoice's figures untouched by the document (AC-08), the document listed with its number and opened from there,
    // still *Terbit* with its failed PDF.
    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    await expect(position('Nilai tagihan')).toHaveText('Rp1.000.000');
    await expect(position('Pembayaran diterapkan')).toHaveText('Rp400.000');
    await expect(position('Sisa tagihan')).toHaveText('Rp600.000');
    await content.getByRole('link', { name: `${NAME} ${number}` }).click();
    await page.waitForURL(new RegExp(`/dokumen/${document}$`));
    await expect(title).toHaveText(`${NAME} ${number}`);
    await expect(content.getByText('PDF Gagal Dibuat', { exact: true })).toBeVisible();

    // The automatic retry (JB-02 → JB-01): the sweeper's tick renders the failed attempt once its one-minute backoff has passed, as the
    // next attempt of the same issued version (BR-DOC-03) — polled as the sweeper runs, never a fixed sleep. *PDF Siap* inline, the number unchanged.
    await expect
        .poll(() => retryRendition(page, document), { message: 'the failed attempt is retried once its backoff has passed (Renditions::isDue)', intervals: [5_000], timeout: 120_000 })
        .toEqual({ state: 'READY', attempt: 2 });
    await content.getByRole('button', { name: 'Muat Ulang' }).click();
    await expect(content.getByText('PDF Siap', { exact: true })).toBeVisible();
    await expect(content.getByText(/PDF gagal dibuat/)).toHaveCount(0);
    await expect(title).toHaveText(`${NAME} ${number}`);
    await expect(version).toContainText('Terbit');
    await expect(version).toContainText(number);
    const frame = content.getByTitle(`PDF ${NAME} ${number}`);
    await expect(frame).toHaveAttribute('src', /^\/berkas\/[0-9a-f-]{36}\/tampil$/);
    const inline = await page.request.get((await frame.getAttribute('src')) ?? '');
    expect(inline.status()).toBe(200);
    expect(inline.headers()['content-type']).toBe('application/pdf');
    expect(inline.headers()['content-disposition']).toMatch(/^inline/);
    expect((await inline.body()).subarray(0, 5).toString()).toBe('%PDF-');
    await expect(content.getByRole('link', { name: 'Unduh' }).first()).toHaveAttribute('href', /\/unduh$/);
    await expect(content.getByRole('link', { name: 'Cetak' })).toHaveAttribute('href', /\/tampil$/);

    await clean(captured, testInfo);
    await guardsClean(page, testInfo, [run.company]);
});
