import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-034's browser fixture (routes/fixtures.php; ConfirmationFixture): the Owner signed in, a company for the run with
// go-live armed and its PROJECT numbering started, a mixed project — a warehouse goods line and a service line — with the
// client's SPK registered as order evidence, confirmed when asked.

export type ConfirmationRun = { company: string; code: string; project: string; number: string; goods: string; service: string };

export async function confirmationRun(page: Page, run: string, options: { confirmed?: boolean; locale?: 'id' | 'en' } = {}): Promise<ConfirmationRun> {
    await ownerSignedIn(page, run, options.locale ?? 'id');
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/konfirmasi', { data: { company, confirmed: options.confirmed ?? false } });

    if (!response.ok()) {
        throw new Error(`The confirmation fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<ConfirmationRun, 'company'>) };
}
