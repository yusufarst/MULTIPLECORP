import { readFile } from 'node:fs/promises';
import { inflateRawSync } from 'node:zlib';

import { type Locator, type Page } from '@playwright/test';

import { invoiceRun, type InvoiceRun } from './invoice-fixtures';

// TASK-063's fixtures for CJ-14 and CJ-15 (routes/fixtures.php; JourneyDocumentFixture) over TASK-049's invoice run, and
// the readers the journeys check their answers with: a report section's rows as the page holds them, an exported
// workbook's rows read from the downloaded file itself (a ZIP of XML parts, read with Node's own zlib, no package), and
// the issued paper's text. CALC-07's buckets are restated here from BR-FIN-15, never read back from the application.

/** CJ-14's older receivable: a second invoice of Rp500,000 issued and billed on `due`, the server's business day `today`. */
export type OlderReceivable = { invoice: string; number: string; due: string; today: string };

/**
 * CJ-14's world: the Owner signed in, the run's company with one issued, unbilled invoice of Rp1,000,000 on its
 * confirmed project, the company's bank account with a transfer proof (TASK-049's and TASK-051's fixture), and the older
 * receivable billed with its due date passed.
 */
export async function receivablesRun(page: Page, run: string): Promise<InvoiceRun & { older: OlderReceivable }> {
    const invoice = await invoiceRun(page, run, { stage: 'issued', bank: true });
    const response = await page.request.post('/__fixtures/journey-b2/piutang', { data: { company: invoice.company } });

    if (!response.ok()) {
        throw new Error(`The older receivable fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { ...invoice, older: (await response.json()) as OlderReceivable };
}

/** PF-28's row bound lowered for this browser context's own requests, its go-live kept as numberingCompany named it. */
export async function exportBound(page: Page, bound: number): Promise<void> {
    await page.context().setExtraHTTPHeaders({ 'X-Fixture-Go-Live': `${String(new Date().getFullYear())}-01-01`, 'X-Fixture-Export-Bound': String(bound) });
}

/**
 * CJ-15: one tick of JB-02's sweeper with its JB-01 run in place of the absent worker — the failed attempt rendered as the next
 * attempt once its backoff has passed, left as it is before —; the latest attempt as it stands after the tick.
 */
export async function retryRendition(page: Page, document: string): Promise<{ state: string; attempt: number }> {
    const response = await page.request.post('/__fixtures/journey-b2/rendisi-ulang', { data: { document } });

    if (!response.ok()) {
        throw new Error(`The rendition retry fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return (await response.json()) as { state: string; attempt: number };
}

/** CJ-15: the text of the issued version's paper as the renderer renders it. */
export async function issuedPaper(page: Page, document: string): Promise<string> {
    const response = await page.request.get(`/__fixtures/journey-b2/isi?dokumen=${encodeURIComponent(document)}`);

    if (!response.ok()) {
        throw new Error(`The issued paper fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return ((await response.json()) as { text: string }).text;
}

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

/** "2026-11-11" as a document writes it: "11 November 2026". */
export function longDate(day: string): string {
    const [year = '', month = '1', date = '1'] = day.split('-');

    return `${String(Number(date))} ${MONTHS[Number(month) - 1] ?? ''} ${year}`;
}

/** "2026-11-11" as the date field and the workbook write it: "11/11/2026". */
export function dmy(day: string): string {
    return day.split('-').reverse().join('/');
}

/**
 * CALC-07 (BR-FIN-15): whole days past the due date, and the bucket's name on PG-044 and in the receivable report —
 * current on and before the due date, then 1–30, 31–60, 61–90 and over 90 days.
 */
export function ageing(due: string, today: string): { days: number; list: string; report: string } {
    const days = Math.max(0, Math.round((Date.parse(`${today}T00:00:00Z`) - Date.parse(`${due}T00:00:00Z`)) / 86_400_000));

    if (days === 0) return { days, list: 'Belum jatuh tempo', report: 'Belum jatuh tempo' };
    if (days <= 30) return { days, list: '1–30 hari', report: 'Lewat 1–30 hari' };
    if (days <= 60) return { days, list: '31–60 hari', report: 'Lewat 31–60 hari' };
    if (days <= 90) return { days, list: '61–90 hari', report: 'Lewat 61–90 hari' };

    return { days, list: 'Lebih dari 90 hari', report: 'Lewat lebih dari 90 hari' };
}

/**
 * A report section's body as the page holds it: each cell's own value — a money or quantity cell's `<data value>`, a
 * date's `<time datetime>`, otherwise its text, "—" read as empty —, the rows apart from the section's total.
 */
export async function reportRows(section: Locator): Promise<{ header: string[]; rows: string[][]; total: string[] | null }> {
    const read = await section.locator('table').evaluate((table) => {
        const value = (cell: Element): string => {
            const text = (cell.querySelector('data')?.getAttribute('value') ?? cell.querySelector('time')?.getAttribute('datetime') ?? cell.textContent).trim();

            return text === '—' ? '' : text;
        };

        return {
            header: Array.from(table.querySelectorAll('thead th'), (cell) => cell.textContent.trim()),
            body: Array.from(table.querySelectorAll('tbody tr'), (row) => ({ total: row.getAttribute('data-slot') === 'section-total', cells: Array.from(row.querySelectorAll('td'), value) })),
        };
    });

    return { header: read.header, rows: read.body.filter((row) => !row.total).map((row) => row.cells), total: read.body.find((row) => row.total)?.cells ?? null };
}

/** The files of a ZIP archive by name, read through its central directory (stored or deflated entries only). */
function unzip(archive: Buffer): Map<string, Buffer> {
    let end = archive.length - 22;

    while (end >= 0 && archive.readUInt32LE(end) !== 0x06054b50) end--;

    if (end < 0) throw new Error('The file is no ZIP archive: it has no end of central directory.');

    const files = new Map<string, Buffer>();
    let at = archive.readUInt32LE(end + 16);

    for (let index = 0; index < archive.readUInt16LE(end + 10); index++) {
        if (archive.readUInt32LE(at) !== 0x02014b50) throw new Error('The archive\'s central directory is damaged.');

        const method = archive.readUInt16LE(at + 10);
        const size = archive.readUInt32LE(at + 20);
        const nameLength = archive.readUInt16LE(at + 28);
        const local = archive.readUInt32LE(at + 42);
        const name = archive.subarray(at + 46, at + 46 + nameLength).toString('utf8');
        const start = local + 30 + archive.readUInt16LE(local + 26) + archive.readUInt16LE(local + 28);
        const raw = archive.subarray(start, start + size);

        if (method !== 0 && method !== 8) throw new Error(`The archive's entry ${name} uses compression method ${String(method)}.`);

        files.set(name, method === 0 ? raw : inflateRawSync(raw));
        at += 46 + nameLength + archive.readUInt16LE(at + 30) + archive.readUInt16LE(at + 32);
    }

    return files;
}

function unescapeXml(text: string): string {
    return text.replace(/&(#x[0-9a-f]+|#\d+|amp|lt|gt|quot|apos);/gi, (_, entity: string) => {
        const named: Record<string, string> = { amp: '&', lt: '<', gt: '>', quot: '"', apos: '\'' };

        if (entity.startsWith('#x') || entity.startsWith('#X')) return String.fromCodePoint(parseInt(entity.slice(2), 16));
        if (entity.startsWith('#')) return String.fromCodePoint(parseInt(entity.slice(1), 10));

        return named[entity.toLowerCase()] ?? '';
    });
}

/** A downloaded workbook's sheet as rows of cell texts: a number as written, an inline string unescaped, an empty cell "". */
export async function workbookRows(path: string, sheet = 1): Promise<string[][]> {
    const part = unzip(await readFile(path)).get(`xl/worksheets/sheet${String(sheet)}.xml`);

    if (part === undefined) throw new Error(`The workbook has no sheet ${String(sheet)}.`);

    const cell = /<c\/>|<c t="n"><v>(.*?)<\/v><\/c>|<c t="inlineStr"><is><t xml:space="preserve">(.*?)<\/t><\/is><\/c>/gs;

    return Array.from(part.toString('utf8').matchAll(/<row>(.*?)<\/row>|<row\/>/gs), (row) => Array.from((row[1] ?? '').matchAll(cell), (found) => unescapeXml(found[1] ?? found[2] ?? '')));
}
