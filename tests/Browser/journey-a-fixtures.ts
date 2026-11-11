import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-062's journey fixture for CJ-01 (routes/fixtures.php; JourneyFixture): the Owner signed in through PG-001 and PG-002, a company
// for the run with go-live armed and the numbering of every document the golden flow issues started, and the run's master data only —
// a client with its unit and PIC, a supplier, a product sold at Rp250,000 (stocked when asked, from a replenishment received before the
// journey), a rack, the company's bank account and the papers that reach it from outside (the supplier's shipment note, the client's
// signed receipts, two transfer proofs). Every business record of a journey is made on its pages; `journeyTrace` reads, read-only, the
// purchases, receipts and stock movements that exist for it, so a path that needs none is shown to have created none (AC-20; GAP-021).

export type JourneyRun = {
    company: string;
    code: string;
    client: string;
    unit: string;
    supplier: string;
    product: string;
    product_id: string;
    rack: string;
    delivery_proof: string;
    supplier_proof: string;
    client_proof: string;
    /** The two transfer proofs' issuers, the first payment's and the second's. */
    payment_proofs: [string, string];
};

export type JourneyTrace = { project_purchases: number; project_receipts: number; company_purchases: number; company_receipts: number; movements: Record<string, number> };

export async function journeyRun(page: Page, run: string, options: { stock?: string } = {}): Promise<JourneyRun> {
    await ownerSignedIn(page, run);
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/jurnal', { data: { company, stock: options.stock ?? '' } });

    if (!response.ok()) {
        throw new Error(`The journey fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<JourneyRun, 'company'>) };
}

/** The purchases, receipts and stock movements that exist for the run's company and product, and for the project when one is named. */
export async function journeyTrace(page: Page, run: JourneyRun, project: string | null): Promise<JourneyTrace> {
    const query = new URLSearchParams({ perusahaan: run.company, produk: run.product_id, ...(project === null ? {} : { proyek: project }) });
    const response = await page.request.get(`/__fixtures/jurnal/jejak?${query.toString()}`);

    if (!response.ok()) {
        throw new Error(`The journey trace failed: ${String(response.status())} ${await response.text()}`);
    }

    return (await response.json()) as JourneyTrace;
}
