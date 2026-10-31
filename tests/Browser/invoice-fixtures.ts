import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-049's browser fixture (routes/fixtures.php; InvoiceFixture): the Owner signed in, a company for the run with go-live armed and the layouts' numbering started, a confirmed service project of ten at Rp250,000 and, by `stage`, a draft of Rp1,000,000 or that invoice issued, with a payment applied when asked.

export type InvoiceRun = { company: string; code: string; project: string; number: string; invoice: string | null; evidence: string; client: string; client_name: string; line: string | null };

export async function invoiceRun(page: Page, run: string, options: { stage?: 'none' | 'draft' | 'issued' | 'moved'; lines?: number; payment?: boolean; tax?: boolean; locale?: 'id' | 'en'; billed?: boolean; writtenOff?: string; credit?: string; recovery?: string; bank?: boolean; line?: string; paid?: string; apply?: boolean } = {}): Promise<InvoiceRun> {
    await ownerSignedIn(page, run, options.locale ?? 'id');
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/invoice', { data: { company, stage: options.stage ?? 'none', lines: options.lines ?? 1, payment: options.payment ?? false, tax: options.tax ?? false, billed: options.billed ?? false, written_off: options.writtenOff, credit: options.credit, recovery: options.recovery, bank: options.bank ?? false, line: options.line, paid: options.paid, apply: options.apply ?? false } });

    if (!response.ok()) {
        throw new Error(`The invoice fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<InvoiceRun, 'company'>) };
}
