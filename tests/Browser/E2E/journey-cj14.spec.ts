import { expect as base, type Locator, type Page, test } from '@playwright/test';

import { ageing, dmy, exportBound, receivablesRun, reportRows, workbookRows } from '../journey-b2-fixtures';
import { clean, guardsClean, watch } from '../journey-fixtures';
import { runExports } from '../report-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-09 (TASK-063; CJ-14, reporting and exports; J-FIN-02; UXS-36; AC-11; AC-23; criteria 3, 7, 8): on the run's own company, at
// the desk — an issued invoice waiting in PG-044's *Belum Ditagihkan* apart from the active receivables, billed from its row with
// its due date (J-FIN-02), Rp400,000 of it paid on PG-046, and an older receivable whose due date has passed: PG-044's subtotal per
// company with its ageing buckets and each row's age (CALC-07). PG-051, reached through the shell, filtered by the company and by a
// date range through its own controls: the sales value, the receivable and its ageing, and the Cash-In each shown apart and
// reconciling — sales 1,500,000 = receivable 1,100,000 + Cash-In 400,000 (AC-11) —; an export above the row bound refused in place
// with MSG-21, a narrower period admitted to PG-053 as *Diminta*, a third request while two are owed refused with MSG-22 (UXS-36;
// PF-28; WS-12), the job's file *Siap Diunduh* and downloaded, its rows equal to the report's rows on the page (AC-23). Its console
// and network captured and ReconcileGuards clean at its end.

// One journey crosses PG-044, PG-043, PG-046, PG-045, PG-051 and PG-053 with three committed commands and three export requests.
test.describe.configure({ timeout: 300_000 });

const SALES_HEADER = ['Nomor', 'Tanggal', 'Klien', 'Proyek', 'Nilai penjualan', 'PPN keluaran', 'Nilai tagihan'];
const MSG_21 = 'Data melebihi batas satu ekspor. Persempit periode, lalu minta lagi.';
const MSG_22 = 'Dua ekspor Anda masih diproses. Tunggu salah satunya selesai.';

/** PG-051 through the shell: the Owner's primary entries hold no *Laporan*, so *Menu lengkap* opens it when it is shut (shell.spec.ts). */
async function openReports(page: Page): Promise<void> {
    const nav = page.getByRole('navigation', { name: 'Navigasi utama' }).first();
    const reports = nav.getByRole('link', { name: 'Laporan', exact: true });

    if (!(await reports.isVisible())) {
        await nav.getByRole('button', { name: 'Menu lengkap' }).click();
    }

    await reports.scrollIntoViewIfNeeded();
    await expect(reports).toBeInViewport({ ratio: 1 });
    await reports.click();
    await page.waitForURL(/\/laporan(\?|$)/);
}

/** One report table's section of the run's company. */
function section(page: Page, table: string, code: string): Locator {
    return page.locator(`[data-table="${table}"] [data-slot="report-section"][data-company="${code}"]`);
}

/** A day typed into one of the range's date fields: focused first, so its long date has turned into the digits it edits (receivables.spec.ts). */
async function typeDay(page: Page, label: string, day: string): Promise<void> {
    const field = page.getByLabel(label);
    await field.focus();
    await field.fill(dmy(day));
}

/**
 * A filter changed through the page's own control, then its address and the deferred `tables` reload that follows it
 * (PT-19): a field typed while that reload lands is re-rendered under the typing — WebKit lost a typed day so —, so
 * the next field is typed only once the page has settled.
 */
async function filtered(page: Page, change: () => Promise<void>, address: RegExp): Promise<void> {
    const tables = page.waitForResponse((response) => response.request().headers()['x-inertia-partial-data'] === 'tables' && address.test(response.url()));
    await change();
    await page.waitForURL(address);
    await tables;
}

/** "Ekspor" → "Ekspor Excel", the page's own export of its report and filters (ROUTE-283). */
async function exportExcel(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Ekspor', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Ekspor Excel' }).click();
}

/** The pages' Rupiah (lib/format's money): "Rp", the thousands with dots, no sen. */
const rupiah = (value: number): string => `Rp${String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;

/** A money value of the report's rows as a number of sen, so the reports' figures are added exactly. */
const sen = (value: string | undefined): number => Math.round(Number(value ?? 'NaN') * 100);

test('CJ-14: receivables with their ageing, the reports by company and date, an export with its row bound and a third request refused, the file equal to the report (J-FIN-02; UXS-36; AC-11; AC-23; criterion 3)', async ({ page }, testInfo) => {
    const captured = watch(page);
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await receivablesRun(page, `cj14-${String(Date.now())}`);
    const { older } = run;
    const age = ageing(older.due, older.today);
    const content = page.locator('#konten');
    const receivables = page.getByRole('table', { name: 'Piutang' });

    // J-FIN-02: the issued invoice waits in *Belum Ditagihkan*, kept apart from the active receivables, and is billed from its row.
    await page.goto(`/keuangan/piutang?perusahaan=${run.company}&tab=belum`);
    await expect(page.getByRole('tab', { name: 'Belum Ditagihkan' })).toHaveAttribute('aria-selected', 'true');
    await expect(receivables).toContainText('Rp1.000.000');
    await expect(receivables).not.toContainText(older.number);
    await page.getByRole('button', { name: 'Tindakan baris' }).click();
    await page.getByRole('menuitem', { name: 'Tagihkan' }).click();
    const bill = page.getByRole('alertdialog');
    await expect(bill).toContainText('Sisa tagihan Rp1.000.000 menjadi piutang aktif dan umurnya dihitung dari tanggal jatuh tempo.');
    await bill.getByRole('textbox', { name: /^Tanggal jatuh tempo/ }).fill('31/12/2099');
    await bill.getByRole('button', { name: 'Tagihkan' }).click();
    await expect(page).toHaveURL(/tab=belum/);
    await expect(content).toContainText('Tidak ada invoice yang menunggu ditagihkan.');

    // The invoice's own page: its number and the billing's due date as recorded.
    await page.goto(`/keuangan/invoice/${run.invoice ?? ''}`);
    const title = page.getByRole('heading', { level: 1 });
    await expect(title).toHaveText(/^Invoice UJI\/INV\/\d{4}\/\d{4}$/);
    const number = ((await title.textContent()) ?? '').replace(/^Invoice /, '');
    await expect(page.getByTestId('billing')).toContainText('31 Desember 2099');

    // J-FIN-03's partial payment, so that the reports have a Cash-In to tell apart from the sale and the receivable.
    await page.goto(`/keuangan/pembayaran/baru?invoice=${run.invoice ?? ''}`);
    await page.getByLabel(/^Jumlah/).fill('400000');
    await page.getByLabel(/^Rekening perusahaan/).selectOption({ index: 1 });
    const proof = page.getByRole('combobox', { name: /^Bukti/ });
    await proof.selectOption({ value: (await proof.locator('option', { hasText: `Bank Uji ${run.code}` }).getAttribute('value')) ?? '' });
    await page.getByTestId('application').filter({ hasText: number }).getByLabel(/^Diterapkan/).fill('400000');
    await expect(page.getByRole('alertdialog')).toHaveCount(0);
    await page.getByRole('button', { name: 'Catat Pembayaran' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Catat Pembayaran' }).click();
    await expect(page).toHaveURL(/\/keuangan\/pembayaran\?.*tercatat=/);
    await expect(page.getByTestId('payment')).toContainText('Rp400.000');

    // PG-044 *Aktif*: the company's subtotal with its ageing buckets (CALC-07), each row with its age, oldest due first.
    const buckets = new Map<string, number>([['Belum jatuh tempo', 600_000]]);
    buckets.set(age.list, (buckets.get(age.list) ?? 0) + 500_000);
    await page.goto(`/keuangan/piutang?perusahaan=${run.company}`);
    await expect(page.getByRole('tab', { name: 'Aktif' })).toHaveAttribute('aria-selected', 'true');
    const subtotals = page.getByTestId('subtotals');
    await expect(subtotals).toContainText(`${run.code} · 2 invoice`);
    await expect(subtotals).toContainText('Rp1.100.000');

    for (const [bucket, amount] of buckets) {
        await expect(subtotals).toContainText(`${bucket}: ${rupiah(amount)}`);
    }

    const olderRow = receivables.getByRole('row').filter({ hasText: older.number });
    const currentRow = receivables.getByRole('row').filter({ hasText: number });
    await expect(olderRow).toContainText('Rp500.000');
    await expect(olderRow).toContainText(age.days > 0 ? `${String(age.days)} hari lewat` : 'Belum jatuh tempo');
    await expect(currentRow).toContainText('Rp600.000');
    await expect(currentRow).toContainText('Belum jatuh tempo');
    await expect(receivables.getByRole('row').nth(1)).toContainText(older.number);

    // PG-051 from the shell, the sales report filtered by the run's company and a date range through the page's own controls.
    await openReports(page);
    await page.getByRole('tab', { name: 'Laporan Penjualan' }).click();
    await page.waitForURL(/laporan=penjualan/);
    await content.getByRole('combobox', { name: 'Perusahaan', exact: true }).selectOption(run.code);
    await page.waitForURL(new RegExp(`perusahaan=${run.code}`));
    await filtered(page, async () => { await page.getByLabel('Periode').selectOption('rentang'); }, /periode=rentang/);
    await filtered(page, () => typeDay(page, 'Sampai tanggal', older.today), new RegExp(`sampai=${older.today}`));
    await typeDay(page, 'Dari tanggal', older.due);
    await page.waitForURL(new RegExp(`dari=${older.due}`));

    const sales = section(page, 'penjualan', run.code);
    await expect(page.locator('[data-table="penjualan"] [data-slot="report-section"]')).toHaveCount(1);
    await expect(sales.locator('[data-slot="section-total"]')).toContainText('Rp1.500.000');
    const wide = await reportRows(sales);
    expect(wide.header).toEqual(SALES_HEADER);
    expect(wide.rows.map((row) => row[0]).sort()).toEqual([number, older.number].sort());
    expect(wide.rows.map((row) => row[1]).sort()).toEqual([older.due, older.today].sort());
    expect(wide.rows.every((row) => row[2] === run.client_name)).toBe(true);

    // The receivable report: a position, as of today — the ageing buckets with their chart, the invoices with a remainder.
    await page.getByRole('tab', { name: 'Laporan Piutang' }).click();
    await page.waitForURL(/laporan=piutang/);
    expect(new URL(page.url()).searchParams.get('perusahaan')).toBe(run.code);
    const ages = section(page, 'umur', run.code);
    await expect(ages.locator('[data-slot="ageing-chart"]')).toBeVisible();
    const ageRows = await reportRows(ages);
    const reportBuckets = new Map<string, number>([['Belum jatuh tempo', 60_000_000]]);
    reportBuckets.set(age.report, (reportBuckets.get(age.report) ?? 0) + 50_000_000);

    for (const row of ageRows.rows) {
        expect(sen(row[1]), `the bucket ${row[0] ?? ''}`).toBe(reportBuckets.get(row[0] ?? '') ?? 0);
    }

    const receivable = sen(ageRows.total?.[1]);
    expect(receivable).toBe(110_000_000);
    const open = await reportRows(section(page, 'rincian', run.code));
    expect(open.rows.map((row) => [row[0], sen(row[4])]).sort()).toEqual([[number, 60_000_000], [older.number, 50_000_000]].sort());

    // The payment report over the same range: the Cash-In alone.
    await page.getByRole('tab', { name: 'Laporan Pembayaran' }).click();
    await page.waitForURL(/laporan=pembayaran/);
    expect(new URL(page.url()).searchParams.get('dari')).toBe(older.due);
    const cash = await reportRows(section(page, 'pembayaran', run.code));
    expect(cash.rows).toHaveLength(1);
    const cashIn = sen(cash.total?.[4]);
    expect(cashIn).toBe(40_000_000);

    // AC-11: the sales value, the active receivable and the Cash-In told apart, and reconciling with the records they come from.
    expect(sen(wide.total?.[6])).toBe(receivable + cashIn);

    // UXS-36 and PF-28: with the run's row bound at one row, the two-invoice range is refused in place with MSG-21, nothing requested.
    await exportBound(page, 1);
    await page.getByRole('tab', { name: 'Laporan Penjualan' }).click();
    await page.waitForURL(/laporan=penjualan/);
    await expect(sales.locator('[data-slot="section-total"]')).toContainText('Rp1.500.000');
    await exportExcel(page);
    await expect(page.getByText(MSG_21)).toBeVisible();
    expect(new URL(page.url()).pathname).toBe('/laporan');

    // The narrower period, as MSG-21 asks: today's one invoice, admitted to PG-053 as *Diminta*.
    await typeDay(page, 'Dari tanggal', older.today);
    await page.waitForURL(new RegExp(`dari=${older.today}`));
    await expect(sales.locator('[data-slot="section-total"]')).toContainText('Rp1.000.000');
    const narrow = await reportRows(sales);
    expect(narrow.rows.map((row) => row[0])).toEqual([number]);
    const report = page.url();
    await exportExcel(page);
    await page.waitForURL('**/laporan/ekspor');
    await expect(page.getByRole('heading', { name: 'Ekspor Saya' })).toBeVisible();
    const exports = page.locator('[data-slot="export-row"]');
    await expect(exports).toHaveCount(1);
    await expect(exports.first()).toContainText('Laporan Penjualan · Excel');
    await expect(exports.first()).toContainText(run.code);
    await expect(exports.first()).toContainText('Diminta');

    // A second request while the first is owed is admitted; a third, while two are, is refused in place with MSG-22 (WS-12).
    await page.goto(report);
    await exportExcel(page);
    await page.waitForURL('**/laporan/ekspor');
    await expect(exports).toHaveCount(2);
    await page.goto(report);
    await exportExcel(page);
    await expect(page.getByText(MSG_22)).toBeVisible();
    expect(new URL(page.url()).pathname).toBe('/laporan');

    // The owed requests generated as the queue worker would; the file downloaded from PG-053 as an ordinary browser download.
    expect(await runExports(page)).toBe(2);
    await page.goto('/laporan/ekspor');
    await expect(exports).toHaveCount(2);
    await expect(exports.filter({ hasText: 'Siap Diunduh' })).toHaveCount(2);
    const download = page.waitForEvent('download');
    await exports.first().getByRole('link', { name: 'Unduh' }).click();
    const file = await download;
    expect(file.suggestedFilename()).toMatch(/^laporan-penjualan-.*\.xlsx$/);

    // AC-23: the file is the report with its filters — its title, the filters as a line, the page's header and rows, the section's total.
    const sheet = await workbookRows(await file.path());
    expect(sheet[0]).toEqual(['Laporan Penjualan']);
    expect(sheet[1]?.[0]).toContain(`Periode: ${dmy(older.today)} – ${dmy(older.today)} · Perusahaan: ${run.code} · Dibuat:`);
    expect(sheet[3]).toEqual(['Perusahaan', ...narrow.header]);
    const asWritten = (cells: string[]): string[] => [run.code, ...cells.map((cell) => (/^\d{4}-\d{2}-\d{2}$/.test(cell) ? dmy(cell) : cell))];
    expect(sheet.slice(4)).toEqual([...narrow.rows.map(asWritten), asWritten(narrow.total ?? [])]);

    await clean(captured, testInfo);
    await guardsClean(page, testInfo, [run.company]);
});
