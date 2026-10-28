import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-029's browser fixtures (routes/fixtures.php; DocumentFixture): the Owner signed in, a company for the run with
// go-live at the year's start, and a draft of a type prepared from a synthetic project through ROUTE-221's action,
// its numbering started; and an issued version's rendition rendered or failed, as the absent queue worker would.

export async function documentDraft(page: Page, run: string, type = 'DOC-09', locale: 'id' | 'en' = 'id'): Promise<{ id: string; company: string }> {
    await ownerSignedIn(page, run, locale);
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const answer = (await (await page.request.post('/__fixtures/dokumen', { data: { company, type } })).json()) as { document: string };

    return { id: answer.document, company };
}

export async function rendition(page: Page, document: string, state: 'READY' | 'FAILED'): Promise<void> {
    await page.request.post('/__fixtures/dokumen/rendisi', { data: { document, state } });
}
