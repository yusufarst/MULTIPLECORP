import { type Page } from '@playwright/test';

import { invoiceRun, type InvoiceRun } from './invoice-fixtures';

// TASK-054's browser fixture over TASK-049's: the Owner signed in, a company with its account and a confirmed project, an open purchase, a transfer proof and a supplier bill of Rp100,000 to cite on PG-049.

export type OtherCashRun = InvoiceRun & { purchase: string; purchase_number: string; transfer_proof: string; bill: string };

export async function otherCashRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<OtherCashRun> {
    const invoiced = await invoiceRun(page, run, { stage: 'none', bank: true, locale });
    const response = await page.request.post('/__fixtures/kas-lain', { data: { company: invoiced.company } });

    if (!response.ok()) {
        throw new Error(`The other-cash fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { ...invoiced, ...((await response.json()) as Pick<OtherCashRun, 'purchase' | 'purchase_number' | 'transfer_proof' | 'bill'>) };
}
