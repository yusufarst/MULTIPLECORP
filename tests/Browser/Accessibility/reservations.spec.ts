import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

import { atRest } from '../evidence-fixtures';
import { reservationRun } from '../reservation-fixtures';

// TL-12 (TASK-038; J-INV-02; DESIGN_SYSTEM §12.1): PG-025 with its "Kurangi Reservasi" dialog and PG-011's "Reservasi"
// dialog pass the WCAG 2.2 AA checks at both bands, each measured at rest; the state says itself in words beside its icon.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

for (const width of [390, 1280]) {
    test(`finds no WCAG 2.2 AA violation on PG-025 and the reservation dialogs at ${String(width)} px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const run = await reservationRun(page, `reservasi-a11y-${String(width)}-${String(Date.now())}`, { reserved: '2' });

        await page.goto(`/gudang/reservasi?cabang=${run.company}`);
        await expect(page.locator('#konten')).toContainText(run.number);
        await expect(page.locator('[data-status="reservation.ACTIVE"]').first()).toHaveText('Aktif');
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);

        if (width >= 768) {
            await page.getByRole('button', { name: 'Tindakan baris' }).click();
            await page.getByRole('menuitem', { name: 'Kurangi Reservasi' }).click();
            await expect(page.getByRole('alertdialog')).toBeVisible();
            await atRest(page);
            expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
            await page.keyboard.press('Escape');
        }

        await page.goto(`/proyek/${run.project}`);
        await page.getByRole('list', { name: 'Baris terkonfirmasi' }).locator('[data-line="1"]').getByRole('button', { name: 'Reservasi', exact: true }).click();
        await expect(page.getByRole('alertdialog')).toBeVisible();
        await atRest(page);
        expect((await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations).toEqual([]);
    });
}
