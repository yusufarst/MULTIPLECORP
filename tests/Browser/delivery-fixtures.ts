import { type Page } from '@playwright/test';

import { stockRun, type StockRun } from './stock-fixtures';

// TASK-045's browser fixture over TASK-040's: three units of the project's warehouse line shipped from the run's lot, a delivery proof of the company and DOC-08's numbering; the signed-in account records, closes and corrects deliveries.

export type DeliveryRun = StockRun & { shipment: string; proof: string };

export async function deliveryRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<DeliveryRun> {
    const stocked = await stockRun(page, run, locale);
    const response = await page.request.post('/__fixtures/deliveries', { data: { company: stocked.company, project: stocked.project, product: stocked.product, lot: stocked.lot, rack: stocked.rack } });

    if (!response.ok()) {
        throw new Error(`The delivery fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { ...stocked, ...((await response.json()) as Pick<DeliveryRun, 'shipment' | 'proof'>) };
}
