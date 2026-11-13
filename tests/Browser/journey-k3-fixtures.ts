import { randomBytes } from 'node:crypto';

import { type Browser, type BrowserContext, expect, type Page, type TestInfo } from '@playwright/test';

import { type watch } from './journey-fixtures';

// TASK-064's preconditions for CJ-12, CJ-16, CJ-19 and CJ-22 (routes/fixtures.php; JourneyMasterFixture): the run's stamps, the business
// day in WIB, go-live armed for a context's own requests, the Admin of a company made for the run, CJ-12's search world, the status
// audit of UXS-42, and CJ-16's FX-09 batches (TEST_STRATEGY §4) — synthetic data only, never a real name, number or credential.

export type Captured = ReturnType<typeof watch>;

const WIB = 7 * 3_600_000;

/** The business day in WIB (BR-DT-01), `YYYY-MM-DD`. */
export function wibToday(): string {
    return new Date(Date.now() + WIB).toISOString().slice(0, 10);
}

/** The business year in WIB, the period of a yearly scheme. */
export function wibYear(): number {
    return Number(wibToday().slice(0, 4));
}

/** A lower-case stamp of letters only, so a term built from it holds a trigram and never reads as a code (Term::looksLikeCode). */
export function letters(length: number): string {
    return Array.from(randomBytes(length), (byte) => 'abcdefghijklmnopqrstuvwxyz'[byte % 26]).join('');
}

export function capitalized(text: string): string {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Go-live recorded on `day` for this context's own requests and armed for the run (NumberingFixture), its store being TASK-077's. */
export async function armGoLive(page: Page, day: string): Promise<void> {
    await page.context().setExtraHTTPHeaders({ 'X-Fixture-Go-Live': day });
    const response = await page.request.post('/__fixtures/penomoran', { data: { go_live: day } });

    if (!response.ok()) {
        throw new Error(`The go-live fixture failed: ${String(response.status())} ${await response.text()}`);
    }
}

/** A second browser context at the desk for another account, with the same go-live header as the run's (journey-cj06's pattern). */
export async function secondContext(browser: Browser, testInfo: TestInfo, goLive?: string): Promise<BrowserContext> {
    return browser.newContext({
        baseURL: testInfo.project.use.baseURL,
        ignoreHTTPSErrors: true,
        viewport: { width: 1280, height: 900 },
        ...(goLive === undefined ? {} : { extraHTTPHeaders: { 'X-Fixture-Go-Live': goLive } }),
    });
}

/** The signed-in plain account made an Admin of the company — `projects.view` and `projects.manage` there, nothing of numbering (JourneyMasterFixture `admin`). */
export async function companyAdmin(page: Page, company: string): Promise<void> {
    const response = await page.request.post('/__fixtures/journey-k3/admin', { data: { company } });

    if (!response.ok()) {
        throw new Error(`The admin fixture failed: ${String(response.status())} ${await response.text()}`);
    }
}

export type SearchStamps = { bulk: string; name: string; hidden: string; prefix: string };

export type SearchWorld = {
    own: { company: string; code: string; project_number: string; document_number: string; client: string; product: string; sku: string; barcode: string; prefixed: string; middle: string };
    other: { company: string; code: string };
};

/**
 * CJ-12's world (JourneyMasterFixture `search`; FX-07): two companies made for the run, each with a project and an issued quotation drawn
 * through their own actions — so both hold the same project and document numbers —, more than 200 projects of the run's own company titled
 * with `bulk` and a few of the other's, a project of the other company alone titled with `hidden`, a product with its SKU and barcode, a
 * client, a product named from the two-character `prefix` and one holding it only inside its name; the signed-in account made an Admin of
 * the run's own company only, with `projects.view`.
 */
export async function searchWorld(page: Page, stamps: SearchStamps): Promise<SearchWorld> {
    const response = await page.request.post('/__fixtures/journey-k3/cari', { data: stamps });

    if (!response.ok()) {
        throw new Error(`The search fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return (await response.json()) as SearchWorld;
}

/** Several pages' captures as one journey's evidence (journey-cj06's pattern). */
export function merged(...captures: Captured[]): Captured {
    // Read live, so each page's deferred decisions (`pending`) and the failures they add are seen when clean() reads them.
    return {
        get console() { return captures.flatMap((captured) => captured.console); },
        get failed() { return captures.flatMap((captured) => captured.failed); },
        get csp() { return captures.flatMap((captured) => captured.csp); },
        get requests() { return captures.reduce((sum, captured) => sum + captured.requests, 0); },
        engine: captures[0]?.engine ?? '',
        get pending() { return captures.flatMap((captured) => captured.pending); },
    };
}

export type ShownStatus = { status: string; family: string; state: string; label: string; shape: string; iconHidden: boolean; iconSize: number; visible: boolean };

/**
 * Every status the page shows (PT-13; UXS-42), as the DOM carries it: its family and state, its text label, its shape — the drawing of its
 * Lucide icon, independent of any colour —, whether the icon is hidden from assistive technology (the label being the cue) and its size.
 */
export async function shownStatuses(page: Page): Promise<ShownStatus[]> {
    return page.locator('[data-status]').evaluateAll((elements) =>
        elements.map((element) => {
            const status = element.getAttribute('data-status') ?? '';
            const [family = '', state = ''] = status.split('.');
            const icon = element.querySelector('svg');
            const box = icon?.getBoundingClientRect();
            const rect = element.getBoundingClientRect();
            const shape = icon === null ? '' : Array.from(icon.children).map((child) => `${child.tagName}:${Array.from(child.attributes).filter((attribute) => attribute.name !== 'class').map((attribute) => `${attribute.name}=${attribute.value}`).sort().join(';')}`).join('|');

            return {
                status,
                family,
                state,
                label: element.textContent.replace(/\s+/g, ' ').trim(),
                shape,
                iconHidden: icon?.getAttribute('aria-hidden') === 'true',
                iconSize: Math.round(box?.width ?? 0),
                visible: rect.width > 0 && rect.height > 0,
            };
        }),
    );
}

/**
 * UXS-42 over the statuses shown: each a text label with a shape cue, the icon never the only cue; within a family two states with different
 * labels have different shapes too, so no two states differ by hue alone — what stays when colour is removed (greyscale) or confused
 * (colour blindness) is the label and the shape. Returns the faces seen, per family.
 */
export function auditStatuses(shown: ShownStatus[], where: string, faces: Map<string, Map<string, { label: string; shape: string }>>): void {
    for (const status of shown.filter((entry) => entry.visible)) {
        expect(status.label, `${where}: ${status.status} carries its text label (UXS-42)`).not.toBe('');
        expect(status.shape, `${where}: ${status.status} carries its shape cue (UXS-42)`).not.toBe('');
        expect(status.iconHidden, `${where}: ${status.status}'s shape is a cue beside the label, hidden from assistive technology`).toBe(true);
        expect(status.iconSize, `${where}: ${status.status}'s shape is drawn`).toBeGreaterThan(0);
        const family = faces.get(status.family) ?? new Map<string, { label: string; shape: string }>();
        const known = family.get(status.state);

        if (known !== undefined) {
            expect({ label: status.label, shape: status.shape }, `${where}: ${status.status} reads the same wherever it is shown`).toEqual(known);
        }

        family.set(status.state, { label: status.label, shape: status.shape });
        faces.set(status.family, family);
    }

    for (const [family, states] of faces) {
        const entries = [...states.entries()];

        for (const [i, [state, face]] of entries.entries()) {
            for (const [other, otherFace] of entries.slice(i + 1)) {
                if (face.label === otherFace.label) {
                    // One face for two server states (a rendition PENDING or RENDERING) is one state as the user reads it.
                    expect(face.shape, `${family}: ${state} and ${other} share a label, so they are one face`).toBe(otherFace.shape);
                } else {
                    expect(face.shape, `${family}: ${state} and ${other} differ by shape as well as label, never by hue alone (UXS-42)`).not.toBe(otherFace.shape);
                }
            }
        }
    }
}

/** A text table as CSV, every cell quoted when it needs to be (RFC 4180). */
export function csv(rows: Record<string, string>[]): Buffer {
    const columns = [...new Set(rows.flatMap((row) => Object.keys(row)))];
    const cell = (value: string): string => (/[",\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value);

    return Buffer.from([columns.join(','), ...rows.map((row) => columns.map((column) => cell(row[column] ?? '')).join(','))].join('\n') + '\n', 'utf8');
}

/**
 * One FX-09 batch: the rows a clean source holds, the rows that must be quarantined beside them — malformed, duplicate, out of scope or a
 * legacy number the seed refuses —, and what PG-058's *Baris bermasalah* must say about each quarantined row (WF-MIG-01; FL-06).
 */
export type Fx09Batch = { kind: string; name: string; clean: Record<string, string>[]; dirty: Record<string, string>[]; findings: string[] };

/** A numbering seed the first batch carries: the type, its period and the last number issued outside the system in it (HO-32). */
export type Fx09Seed = { jenis: string; periode: string; last: string };

/**
 * FX-09 (TEST_STRATEGY §4; brief §45; DATABASE §24; AC-17): opening-import batches of synthetic legacy data, because historical data is never
 * assumed clean — clean rows, malformed rows, duplicates, a row of a company outside the preparer's scope and a legacy number with the seeds
 * after it (HO-32; NM-08; AX-28). `code` is the company the batches open, `outside` a company the preparer holds no grant on, `run` the run's
 * stamp keeping each legacy identity the run's own (a second run never meets the first's twins), `cutoff` the batches' date. The seeds — by
 * default the PROJECT and PURCHASE periods of the cutoff's year — ride on the first batch, so every later batch finds them committed
 * (SeedRows::missing). The batches are in their commit order: locations, products and suppliers before the facts that name them.
 */
export function fx09(code: string, outside: string, run: string, cutoff: string, seeds?: Fx09Seed[]): Fx09Batch[] {
    const tag = run.toUpperCase();
    const year = cutoff.slice(0, 4);
    const seeded = (seeds ?? [{ jenis: 'PROJECT', periode: year, last: '35' }, { jenis: 'PURCHASE', periode: year, last: '20' }]).map(
        (seed): Record<string, string> => ({ perusahaan: code, nomor_jenis: seed.jenis, nomor_periode: seed.periode, nomor_terakhir: seed.last }),
    );

    return [
        {
            kind: 'LOCATION',
            name: `fx09-lokasi-${run}.csv`,
            clean: [{ id: `L1-${tag}`, kode: `RAK-${tag}-1`, nama: 'Rak depan' }, { id: `L2-${tag}`, kode: `RAK-${tag}-2`, nama: 'Rak belakang' }, ...seeded],
            // Malformed: the required code empty. Duplicate: the same legacy identity twice in one source.
            dirty: [
                { id: `L3-${tag}`, kode: '', nama: 'Rak tanpa kode' },
                { id: `L1-${tag}`, kode: `RAK-${tag}-1`, nama: 'Rak depan (ganda)' },
            ],
            findings: ['kode: wajib diisi', 'identitas yang sama sudah ada di baris sebelumnya'],
        },
        {
            kind: 'PRODUCT',
            name: `fx09-produk-${run}.csv`,
            clean: [
                { id: `P1-${tag}`, jenis: 'barang', sku: `FX9-${tag}-1`, nama: `Kertas HVS ${tag}`, satuan: 'RIM', nama_satuan: 'Rim', satuan_lain: 'BOX', nama_satuan_lain: 'Kotak', rasio: '5', harga_beli: '45000', harga_jual: '52000' },
                { id: `P2-${tag}`, jenis: 'barang', sku: `FX9-${tag}-2`, nama: `Proyektor ${tag}`, satuan: 'UNIT', serial: 'ya' },
            ],
            // Malformed: a serialized service, and a product without its unit.
            dirty: [
                { id: `P3-${tag}`, jenis: 'jasa', nama: `Instalasi ${tag}`, satuan: 'JAM', serial: 'ya' },
                { id: `P4-${tag}`, jenis: 'barang', nama: `Kabel ${tag}`, satuan: '' },
            ],
            findings: ['produk berserial harus barang dengan jumlah bulat', 'satuan: wajib diisi'],
        },
        {
            kind: 'SUPPLIER',
            name: `fx09-pemasok-${run}.csv`,
            clean: [{ id: `V1-${tag}`, nama: `Pemasok FX9 ${tag}`, kota: 'Sleman' }],
            // Malformed: the required name empty.
            dirty: [{ id: `V2-${tag}`, nama: '', kota: 'Bantul' }],
            findings: ['nama: wajib diisi'],
        },
        {
            kind: 'OPENING_STOCK',
            name: `fx09-stok-${run}.csv`,
            clean: [{ id: `S1-${tag}`, perusahaan: code, produk: `FX9-${tag}-1`, lokasi: `RAK-${tag}-1`, kondisi: 'baik', jumlah: '40', satuan: 'RIM', biaya: '1800000' }],
            // Malformed: a negative quantity. Out of scope: a company the preparer holds no grant on (DP-18; UXS-19).
            dirty: [
                { id: `S3-${tag}`, perusahaan: code, produk: `FX9-${tag}-1`, lokasi: `RAK-${tag}-2`, kondisi: 'baik', jumlah: '-1', satuan: 'RIM', biaya: '45000' },
                { id: `S2-${tag}`, perusahaan: outside, produk: `FX9-${tag}-1`, lokasi: `RAK-${tag}-2`, kondisi: 'baik', jumlah: '5', satuan: 'RIM', biaya: '225000' },
            ],
            findings: ['jumlah: tidak boleh negatif'],
        },
        {
            kind: 'OPEN_PURCHASE',
            name: `fx09-pembelian-${run}.csv`,
            // A legacy purchase number kept below its period's committed seed (NM-08; AX-28; DIR-079 CB-01), its supplier by its legacy identity.
            clean: [{ id: `B1-${tag}`, perusahaan: code, pemasok: `V1-${tag}`, tanggal: cutoff, nomor: `LAMA/BELI/${year}/0017`, produk: `FX9-${tag}-1`, jumlah: '10', satuan: 'RIM', harga: '45000' }],
            // Malformed: an impossible date.
            dirty: [{ id: `B3-${tag}`, perusahaan: code, pemasok: `V1-${tag}`, tanggal: '31-13-2026', produk: `FX9-${tag}-1`, jumlah: '1', satuan: 'RIM', harga: '45000' }],
            findings: ['tanggal: tanggal tidak valid'],
        },
    ];
}
