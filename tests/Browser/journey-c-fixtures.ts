import { type Page } from '@playwright/test';

import { numberingCompany } from './numbering-fixtures';
import { projectRun, type ProjectRun } from './project-fixtures';
import { purchaseRun, type PurchaseRun } from './purchase-fixtures';

// TASK-062's starting preconditions for CJ-05, CJ-07 and CJ-08 (routes/fixtures.php; JourneyStockFixture) over the
// module fixtures — master data and stock received through the real receipt, never a step the journeys walk —, and the
// keyboard-wedge scanner the journeys simulate (IP-15; TEST_STRATEGY §12).

export type CountJourney = PurchaseRun & { other: string; product: string; name: string; sku: string; rack: string; lot: string; pending_product: string; pending_name: string; pending_rack: string; other_code: string };

/**
 * CJ-05's world: the Owner, the company and its confirmed project of ten units (TASK-037's fixture), the other company,
 * ten usable units of the project's product at a rack — nothing reserved, nothing unusable —, a second product held by
 * both companies at another rack, and a pool proof to cite (TASK-042's fixture).
 */
export async function countJourney(page: Page, run: string): Promise<CountJourney> {
    const purchased = await purchaseRun(page, run);
    const other = await numberingCompany(page, `${String(new Date().getFullYear())}-01-01`);
    const response = await page.request.post('/__fixtures/journey-c/stok', { data: { company: purchased.company, other, project: purchased.project } });

    if (!response.ok()) {
        throw new Error(`The journey's stock fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    const world = (await response.json()) as Omit<CountJourney, keyof PurchaseRun | 'other'>;
    const proof = await page.request.post('/__fixtures/adjustments', { data: {} });

    if (!proof.ok()) {
        throw new Error(`The adjustment fixture failed: ${String(proof.status())}`);
    }

    return { ...purchased, other, ...world };
}

/** CJ-07's ambiguous code (UXS-34): the project's product by its barcode and another product by its SKU. */
export async function twinCode(page: Page, project: string): Promise<{ code: string; name: string }> {
    const response = await page.request.post('/__fixtures/journey-c/kembar', { data: { project } });

    if (!response.ok()) {
        throw new Error(`The journey's twin-code fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return (await response.json()) as { code: string; name: string };
}

/** CJ-08's world: TASK-032's company, client and service, then the invoice numbering, a SIPLAH order, an account with a transfer proof and SIPLAH's fee advice of 300,000. */
export async function siplahRun(page: Page, run: string): Promise<ProjectRun> {
    const project = await projectRun(page, run);
    const response = await page.request.post('/__fixtures/journey-c/siplah', { data: { company: project.company } });

    if (!response.ok()) {
        throw new Error(`The journey's SIPLAH fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return project;
}

/**
 * A keyboard-wedge scanner into the focused field. It types faster than the runner's key events travel — a loaded CI
 * runner can space them past the 35 ms burst gap —, so the burst is played inside the page as a scanner delivers it,
 * each character's keydown and insertion and then the Enter, as search.spec.ts and components.spec.ts do. Answers
 * whether the Enter was withheld from its default.
 */
export async function scanBurst(page: Page, code: string): Promise<boolean> {
    return page.evaluate((typed) => {
        const target = document.activeElement ?? document.body;
        const value = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
        let withheld = false;

        for (const key of [...Array.from(typed), 'Enter']) {
            const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true });

            if (!target.dispatchEvent(event)) {
                withheld = key === 'Enter' || withheld;
            } else if (key !== 'Enter' && target instanceof HTMLInputElement) {
                value?.set?.call(target, target.value + key);
                target.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        return withheld;
    }, code);
}
