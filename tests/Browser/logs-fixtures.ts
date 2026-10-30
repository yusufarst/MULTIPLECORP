import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-061's browser fixture (routes/fixtures.php; LogsFixture): the Owner signed in, a company for the run, 55 audit
// events of it with a before and after, one event without a company, a failed sign-in and an authorization anomaly
// whose key a security notice carries to PG-064 (INT-020).

export type LogsRun = { company: string; code: string; tag: string; notice: string };

export async function logsRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<LogsRun> {
    await ownerSignedIn(page, run, locale);
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/log', { data: { company } });

    if (!response.ok()) {
        throw new Error(`The logs fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<LogsRun, 'company'>) };
}
