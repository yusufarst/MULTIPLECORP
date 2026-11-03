import { type Page } from '@playwright/test';

import { invoiceRun, type InvoiceRun } from './invoice-fixtures';

// TASK-055's browser fixture over TASK-049's: the Owner signed in and a confirmed service project, ready to complete — its remainder cancelled — or with its fulfilment still open.

export async function completionRun(page: Page, run: string, ready: boolean, locale: 'id' | 'en' = 'id'): Promise<InvoiceRun> {
    const invoiced = await invoiceRun(page, run, { stage: 'none', locale });
    const response = await page.request.post('/__fixtures/penyelesaian', { data: { project: invoiced.project, ready } });

    if (!response.ok()) {
        throw new Error(`The completion fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return invoiced;
}
