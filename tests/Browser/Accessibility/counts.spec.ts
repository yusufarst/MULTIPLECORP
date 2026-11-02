import { expect as base, test } from '@playwright/test';

import { adjustmentRun } from '../adjustment-fixtures';
import { countLine, openCount } from '../count-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-043; IP-09; DESIGN_SYSTEM §15): the count's fields by their names, the reason reachable and announced, the scan feedback's live region.

test.describe.configure({ timeout: 240_000 });

test('names the count\'s fields and the reason on PG-026, the scan answers in its live region', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await adjustmentRun(page, `opname-a11y-${String(Date.now())}`);

    await openCount(page, run.product, run.rack);
    const scan = page.locator('[data-slot="count-line"]').getByRole('textbox', { name: 'Pindai atau ketik kode' });
    await scan.fill('TIDAK-ADA-KODE');
    await scan.press('Enter');
    await expect(page.locator('[data-slot="count-line"]').getByRole('alert')).toContainText('tidak dikenal');

    await countLine(page, '11');
    await page.locator('[data-slot="count-lot"]').filter({ hasText: run.lot }).first().getByLabel(/Jumlah dari/).fill('1');
    await page.locator('[data-slot="count-apply"]').getByLabel('Bukti', { exact: true }).selectOption({ index: 1 });
    await page.locator('[data-slot="count-apply"]').getByRole('button', { name: 'Terapkan Selisih' }).click();
    await expect(page.getByRole('alertdialog').getByLabel('Alasan')).toBeFocused();
});
