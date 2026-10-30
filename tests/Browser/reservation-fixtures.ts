import { type Page } from '@playwright/test';

import { numberingCompany, ownerSignedIn } from './numbering-fixtures';

// TASK-038's browser fixture (routes/fixtures.php; ReservationFixture): the Owner signed in, a company for the run with
// go-live armed and its PROJECT numbering started, a confirmed project of one warehouse goods line, an opening lot of
// `stock` usable units of its product and, when asked, `reserved` of them reserved for the line.

export type ReservationRun = { company: string; code: string; project: string; number: string; goods: string };

export async function reservationRun(page: Page, run: string, options: { stock?: string; reserved?: string; locale?: 'id' | 'en' } = {}): Promise<ReservationRun> {
    await ownerSignedIn(page, run, options.locale ?? 'id');
    const company = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/reservasi', { data: { company, stock: options.stock ?? '8', reserved: options.reserved ?? '0' } });

    if (!response.ok()) {
        throw new Error(`The reservation fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { company, ...((await response.json()) as Omit<ReservationRun, 'company'>) };
}
