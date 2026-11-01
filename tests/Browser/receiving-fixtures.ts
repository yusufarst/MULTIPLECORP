import { expect as base, type Locator, type Page } from '@playwright/test';

import { purchaseRun, type PurchaseRun } from './purchase-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TASK-039's browser fixture (routes/fixtures.php; ReceivingFixture) over TASK-037's: the Nota's numbering, a rack, a purchase of a serialized product, a purchase of the confirmed project's demand. A keyboard-wedge scanner simulated: a burst of keys into the focused field, then Enter.

export type ReceivingRun = PurchaseRun & { purchase: string; rack: string; sku: string; serial_purchase: string; serial_number: string; serial_name: string; project_purchase: string };

export async function receivingRun(page: Page, run: string, locale: 'id' | 'en' = 'id'): Promise<ReceivingRun> {
    const purchased = await purchaseRun(page, run, locale, true);
    const response = await page.request.post('/__fixtures/receiving', { data: { company: purchased.company, project: purchased.project } });

    if (!response.ok()) {
        throw new Error(`The receiving fixture failed: ${String(response.status())} ${await response.text()}`);
    }

    return { ...purchased, purchase: purchased.purchase ?? '', ...((await response.json()) as Pick<ReceivingRun, 'rack' | 'sku' | 'serial_purchase' | 'serial_number' | 'serial_name' | 'project_purchase'>) };
}

/** The current line on PG-022, the others' forms hidden. */
export function currentLine(page: Page): Locator {
    return page.locator('[data-slot="receive-line"]:not([hidden])');
}

/** A scanner's keystroke burst into the scan field, ended by its Enter (IP-15). */
export async function scan(page: Page, code: string): Promise<void> {
    await page.getByRole('textbox', { name: 'Pindai atau ketik kode' }).click();
    await page.keyboard.type(code, { delay: 5 });
    await page.keyboard.press('Enter');
}

/** The current line's "Catat Barang Masuk" through its confirmation. */
export async function record(page: Page): Promise<void> {
    await currentLine(page).getByRole('button', { name: 'Catat Barang Masuk' }).click();
    await page.getByRole('alertdialog', { name: 'Catat barang masuk ini?' }).getByRole('button', { name: 'Catat Barang Masuk' }).click();
}

export async function fillLine(page: Page, usable: string, rack: string): Promise<void> {
    const line = currentLine(page);
    await expect(line).toBeVisible();
    await line.getByRole('textbox', { name: 'Layak pakai' }).fill(usable);
    await line.getByRole('combobox', { name: 'Rak' }).selectOption({ label: await rackLabel(line, rack) });
}

async function rackLabel(line: Locator, rack: string): Promise<string> {
    const options = await line.getByRole('combobox', { name: 'Rak' }).locator('option').allTextContents();

    return options.find((option) => option.startsWith(rack)) ?? rack;
}
