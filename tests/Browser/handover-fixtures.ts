import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-047's browser fixture (routes/fixtures.php; HandoverFixture): the Owner signed in, a company for the run with go-live
// armed and its PROJECT and DOC-08 numbering started, a confirmed service-only project of `lines` lines of 10, a delivery proof
// to cite and, when asked, a handover of 4 already recorded.

export type HandoverRun = { company: string; code: string; project: string; number: string; service: string };

export async function handoverRun(page: Page, run: string, options: { lines?: number; recorded?: boolean; locale?: 'id' | 'en' } = {}): Promise<HandoverRun> {
    await ownerSignedIn(page, run, options.locale ?? 'id');
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/serah-terima', { data: { company, lines: options.lines ?? 1, recorded: options.recorded ?? false } });

    if (!response.ok()) {
        throw new Error(`The handover fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<HandoverRun, 'company'>) };
}
