import { type Page } from '@playwright/test';

import { invoiceRun, type InvoiceRun } from './invoice-fixtures';

// TASK-053's browser fixture over TASK-049's and TASK-051's: an issued invoice of Rp1,000,000, a Rp1,200,000 payment applying 1,000,000 with its proof — Rp200,000 of credit — and a transfer proof to choose; the signed-in account refunds and holds credit.

export type CreditRun = InvoiceRun & { payment: string; transfer_proof: string };

export async function creditRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<CreditRun> {
    const invoiced = await invoiceRun(page, run, { stage: 'issued', bank: true, locale });
    const response = await page.request.post('/__fixtures/credit', { data: { company: invoiced.company, invoice: invoiced.invoice } });

    if (!response.ok()) {
        throw new Error(`The credit fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { ...invoiced, ...((await response.json()) as Pick<CreditRun, 'payment' | 'transfer_proof'>) };
}
