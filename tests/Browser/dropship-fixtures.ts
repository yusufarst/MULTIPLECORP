import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-046's browser fixture (routes/fixtures.php; DropshipFixture): the Owner signed in, a confirmed project whose line ships direct to the client, its purchase of that line, a delivery proof and DOC-03's numbering.

export type DropshipRun = { company: string; project: string; project_number: string; purchase: string; supplier: string; proof: string; proof_label: string };

export async function dropshipRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<DropshipRun> {
    await ownerSignedIn(page, run, locale);
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/dropship', { data: { company } });

    if (!response.ok()) {
        throw new Error(`The drop-ship fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<DropshipRun, 'company'>) };
}
