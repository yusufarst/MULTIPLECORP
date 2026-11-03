import { type Page } from '@playwright/test';

import { deliveryRun, type DeliveryRun } from './delivery-fixtures';

// TASK-048's browser fixture over TASK-045's: the three shipped units recorded as received by the client and a return proof of the company; the signed-in account takes goods back, reverses a return and closes its case.

export type ClientReturnRun = DeliveryRun & { project_id: string; return_proof: string; rack_handle: string };

export async function clientReturnRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<ClientReturnRun> {
    const delivered = await deliveryRun(page, run, locale);
    const response = await page.request.post('/__fixtures/client-returns', { data: { company: delivered.company, shipment: delivered.shipment, proof: delivered.proof, rack: delivered.rack } });

    if (!response.ok()) {
        throw new Error(`The client-return fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { ...delivered, ...((await response.json()) as Pick<ClientReturnRun, 'project_id' | 'return_proof' | 'rack_handle'>) };
}
