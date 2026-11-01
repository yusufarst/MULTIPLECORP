import AxeBuilder from '@axe-core/playwright';
import { expect as base, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { currentLine, fillLine, receivingRun, record, scan } from '../receiving-fixtures';

const expect = base.configure({ timeout: 60_000 });

// TL-12 (TASK-039; IP-15 step 10; UXI-34; DESIGN_SYSTEM §13.2, §15): PG-021, PG-022 and their confirmation at the desktop and phone widths; each scan announced in a polite live region, a scan error assertively; the disabled record's reason announced.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test.describe.configure({ timeout: 300_000 });

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-021 and PG-022 at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await receivingRun(page, `terima-a11y-${String(width)}-${String(Date.now())}`);

        await page.goto('/gudang/barang-masuk');
        await expect(page.locator('#konten')).toContainText(run.supplier);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await page.goto(`/gudang/barang-masuk/${run.purchase}`);
        await expect(currentLine(page)).toBeVisible();
        await expect(currentLine(page).getByRole('button', { name: 'Catat Barang Masuk' })).toHaveAttribute('aria-describedby', /reason-/);
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        await fillLine(page, '4', run.rack);
        await currentLine(page).getByRole('button', { name: 'Catat Barang Masuk' }).click();
        const dialog = page.getByRole('alertdialog', { name: 'Catat barang masuk ini?' });
        await expect(dialog).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).include('[role="alertdialog"]').analyze()).violations).toEqual([]);
        await dialog.getByRole('button', { name: 'Catat Barang Masuk' }).click();
        await expect(currentLine(page).getByText('Barang masuk tercatat.')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}

test('announces a scan politely and a scan error assertively, the field keeping its focus (IP-15 step 10; UXI-34)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    const run = await receivingRun(page, `terima-umum-${String(Date.now())}`);
    await page.goto(`/gudang/barang-masuk/${run.purchase}`);

    await scan(page, run.sku);
    await expect(page.locator('[data-slot="scan-field"] [aria-live="polite"]').filter({ hasText: 'dibuka' }).first()).toBeAttached();
    await expect(page.getByRole('textbox', { name: 'Pindai atau ketik kode' })).toBeFocused();

    await scan(page, 'TIDAK-ADA-2');
    await expect(page.locator('[data-slot="scan-field"] [role="alert"]').filter({ hasText: 'TIDAK-ADA-2' }).first()).toBeAttached();
    await expect(page.getByRole('textbox', { name: 'Pindai atau ketik kode' })).toBeFocused();

    await fillLine(page, '1', run.rack);
    await record(page);
    await expect(currentLine(page).getByText('Barang masuk tercatat.')).toBeVisible();
});
