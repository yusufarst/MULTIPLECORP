import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-031's browser fixture (routes/fixtures.php; CaseFixture): the Owner signed in, a company for the run, and two of
// its correction cases — a closable sales return and a payment correction still carrying an unresolved deduction.

export async function correctionCases(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<{ company: string; project: string }> {
    await ownerSignedIn(page, run, locale);
    const company = await numberingCompany(page, null);
    const answer = (await (await page.request.post('/__fixtures/kasus', { data: { company } })).json()) as { project: string };

    return { company, project: answer.project };
}
