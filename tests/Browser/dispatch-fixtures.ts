import { type Page } from '@playwright/test';

import { stockRun, type StockRun } from './stock-fixtures';

// TASK-041's browser fixture over TASK-040's: the project's confirmed warehouse line with ON HAND 14 in the pool — 10 of the company's own, 2 of them unusable, 3 reserved —, 4 of another company's; DOC-03's numbering in place.

export async function dispatchRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<StockRun> {
    const stocked = await stockRun(page, run, locale);
    const response = await page.request.post('/__fixtures/dispatch', { data: { company: stocked.company } });

    if (!response.ok()) {
        throw new Error(`The dispatch fixture failed: ${String(response.status())}`);
    }

    return stocked;
}
