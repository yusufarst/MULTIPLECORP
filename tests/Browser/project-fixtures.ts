import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-032's browser fixture (routes/fixtures.php; ProjectFixture): the Owner signed in, a company for the run with
// go-live armed and its PROJECT numbering started — or not, for UXS-28 —, a client with its unit and PIC, goods and a
// service, and optionally a project created through ROUTE-151's action, a DRAFT when asked.

export type ProjectRun = { company: string; code: string; client: string; unit: string; contact: string; goods: string; service: string; project: string | null; number: string | null };

export async function projectRun(page: Page, run: string, options: { numbered?: boolean; project?: boolean; draft?: boolean; locale?: 'id' | 'en' } = {}): Promise<ProjectRun> {
    await ownerSignedIn(page, run, options.locale ?? 'id');
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const answer = (await (await page.request.post('/__fixtures/proyek', {
        data: { company, numbered: options.numbered ?? true, project: options.project ?? false, draft: options.draft ?? false },
    })).json()) as Omit<ProjectRun, 'company'>;

    return { company, ...answer };
}
