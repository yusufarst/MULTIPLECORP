import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-033's browser fixture (routes/fixtures.php; QuotationFixture): the Owner signed in, a company for the run with
// go-live armed and its PROJECT and DOC-01 numbering started, a project of `lines` goods and, by `stage`, its quotation
// drafted with a validity and its prices or also issued.

export type QuotationRun = { company: string; code: string; client: string; project: string; number: string };

export async function quotationRun(page: Page, run: string, options: { lines?: number; stage?: 'none' | 'draft' | 'sent'; locale?: 'id' | 'en' } = {}): Promise<QuotationRun> {
    await ownerSignedIn(page, run, options.locale ?? 'id');
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/penawaran', { data: { company, lines: options.lines ?? 1, stage: options.stage ?? 'none' } });

    if (!response.ok()) {
        throw new Error(`The quotation fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<QuotationRun, 'company'>) };
}
