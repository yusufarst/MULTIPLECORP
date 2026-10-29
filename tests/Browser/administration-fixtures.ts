import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-035's browser fixture (routes/fixtures.php; AdministrationFixture): the Owner signed in, a company for the run
// with go-live armed, TASK-034's confirmed mixed project — its default invoice item —, a required company SPK, the
// school's order letter still to be uploaded, and the client's SPK uploaded as an authentic upload, ready to be linked.

export type AdministrationRun = { company: string; code: string; project: string; number: string; goods: string; service: string; issuer: string };

export async function administrationRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<AdministrationRun> {
    await ownerSignedIn(page, run, locale);
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/administrasi', { data: { company } });

    if (!response.ok()) {
        throw new Error(`The administration fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<AdministrationRun, 'company'>) };
}
